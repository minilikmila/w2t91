<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

class AuditService
{
    /**
     * Log an audit event with hash chaining for tamper evidence.
     */
    public function log(
        string $eventType,
        string $entityType,
        int $entityId,
        ?int $actorId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?string $ipAddress = null,
        string $actorType = 'user'
    ): AuditEvent {
        return DB::transaction(function () use ($eventType, $entityType, $entityId, $actorId, $oldValues, $newValues, $description, $ipAddress, $actorType) {
            // Get the hash of the most recent audit event for chain linking
            $lastEvent = AuditEvent::orderBy('id', 'desc')->first();
            $priorHash = $lastEvent ? $lastEvent->event_hash : null;

            $now = now();

            // Build the hash payload
            $hashPayload = json_encode([
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'description' => $description,
                'prior_hash' => $priorHash,
                'created_at' => $now->toIso8601String(),
            ]);

            $eventHash = hash('sha256', $hashPayload);

            return AuditEvent::create([
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'description' => $description,
                'ip_address' => $ipAddress,
                'prior_hash' => $priorHash,
                'event_hash' => $eventHash,
                'created_at' => $now,
            ]);
        });
    }

    /**
     * Verify the integrity of the audit chain.
     * Returns the number of valid and invalid links.
     */
    public function verifyChain(?int $limit = null): array
    {
        $query = AuditEvent::orderBy('id', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        $events = $query->get();
        $valid = 0;
        $invalid = 0;
        $errors = [];
        $previousHash = null;

        foreach ($events as $event) {
            // Check that prior_hash matches the previous event's event_hash
            if ($event->prior_hash !== $previousHash) {
                $invalid++;
                $errors[] = [
                    'event_id' => $event->id,
                    'error' => 'prior_hash mismatch',
                    'expected' => $previousHash,
                    'actual' => $event->prior_hash,
                ];
            } else {
                // Recompute the event's own hash from stored fields to detect row tampering
                $recomputedPayload = json_encode([
                    'event_type' => $event->event_type,
                    'entity_type' => $event->entity_type,
                    'entity_id' => $event->entity_id,
                    'actor_id' => $event->actor_id,
                    'actor_type' => $event->actor_type,
                    'old_values' => $event->old_values,
                    'new_values' => $event->new_values,
                    'description' => $event->description,
                    'prior_hash' => $event->prior_hash,
                    'created_at' => $event->created_at->toIso8601String(),
                ]);
                $recomputedHash = hash('sha256', $recomputedPayload);

                if ($recomputedHash !== $event->event_hash) {
                    $invalid++;
                    $errors[] = [
                        'event_id' => $event->id,
                        'error' => 'event_hash tampered',
                        'expected' => $recomputedHash,
                        'actual' => $event->event_hash,
                    ];
                } else {
                    $valid++;
                }
            }

            $previousHash = $event->event_hash;
        }

        return [
            'total_events' => $events->count(),
            'valid_links' => $valid,
            'invalid_links' => $invalid,
            'chain_intact' => $invalid === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Query audit events with filters.
     */
    public function query(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = AuditEvent::with('actor');

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (!empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 25), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
