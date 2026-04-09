<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\LearnerIdentifier;

class DeduplicationService
{
    private DataNormalizationService $normalizer;

    public function __construct(DataNormalizationService $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    /**
     * Generate a deterministic fingerprint for a learner based on core identity fields.
     * Uses normalized first_name + last_name + date_of_birth as the base.
     */
    public function generateFingerprint(array $data): string
    {
        $parts = [];

        $firstName = $this->normalizer->normalizeName($data['first_name'] ?? '');
        $lastName = $this->normalizer->normalizeName($data['last_name'] ?? '');
        $dob = $this->normalizer->normalizeDate($data['date_of_birth'] ?? '');

        $parts[] = mb_strtolower(trim($firstName ?? ''));
        $parts[] = mb_strtolower(trim($lastName ?? ''));
        $parts[] = $dob ?? '';

        $raw = implode('|', $parts);

        return hash('sha256', $raw);
    }

    /**
     * Generate an identifier-level fingerprint for deduplication on specific fields.
     */
    public function generateIdentifierFingerprint(string $type, string $value): string
    {
        $normalizedValue = match ($type) {
            'email' => $this->normalizer->normalizeEmail($value),
            'phone' => $this->normalizer->normalizePhone($value),
            default => mb_strtolower(trim($value)),
        };

        return hash('sha256', "{$type}|{$normalizedValue}");
    }

    /**
     * Compute and store the learner fingerprint.
     */
    public function computeAndStoreFingerprint(Learner $learner): string
    {
        $fingerprint = $this->generateFingerprint($learner->toArray());

        $learner->update(['fingerprint' => $fingerprint]);

        return $fingerprint;
    }

    /**
     * Register a learner identifier and check for duplicates.
     */
    public function registerIdentifier(Learner $learner, string $type, string $value, bool $isPrimary = false): LearnerIdentifier
    {
        $fingerprint = $this->generateIdentifierFingerprint($type, $value);

        $identifier = LearnerIdentifier::create([
            'learner_id' => $learner->id,
            'type' => $type,
            'value' => $value,
            'fingerprint' => $fingerprint,
            'is_primary' => $isPrimary,
        ]);

        // Check for existing identifiers with the same fingerprint belonging to other learners
        $duplicates = LearnerIdentifier::where('fingerprint', $fingerprint)
            ->where('learner_id', '!=', $learner->id)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $identifier->update([
                'is_duplicate_candidate' => true,
                'duplicate_of_learner_id' => $duplicates->first()->learner_id,
                'duplicate_status' => 'pending_review',
            ]);
        }

        return $identifier;
    }

    /**
     * Find potential duplicate learners by fingerprint.
     */
    public function findDuplicatesByFingerprint(Learner $learner): array
    {
        if (!$learner->fingerprint) {
            return [];
        }

        return Learner::where('fingerprint', $learner->fingerprint)
            ->where('id', '!=', $learner->id)
            ->get()
            ->toArray();
    }

    /**
     * Find potential duplicates using multiple identity fields.
     */
    public function findDuplicateCandidates(array $data): array
    {
        $candidates = collect();

        // Check by learner fingerprint
        $fingerprint = $this->generateFingerprint($data);
        $byFingerprint = Learner::where('fingerprint', $fingerprint)->get();
        $candidates = $candidates->merge($byFingerprint);

        // Check by email
        if (!empty($data['email'])) {
            $email = $this->normalizer->normalizeEmail($data['email']);
            $byEmail = Learner::where('email', $email)->get();
            $candidates = $candidates->merge($byEmail);
        }

        // Check by phone
        if (!empty($data['phone'])) {
            $phone = $this->normalizer->normalizePhone($data['phone']);
            $byPhone = Learner::where('phone', $phone)->get();
            $candidates = $candidates->merge($byPhone);
        }

        // Check by identifier fingerprints
        $identifierFingerprints = [];
        if (!empty($data['email'])) {
            $identifierFingerprints[] = $this->generateIdentifierFingerprint('email', $data['email']);
        }
        if (!empty($data['phone'])) {
            $identifierFingerprints[] = $this->generateIdentifierFingerprint('phone', $data['phone']);
        }

        if (!empty($identifierFingerprints)) {
            $byIdentifiers = LearnerIdentifier::whereIn('fingerprint', $identifierFingerprints)
                ->with('learner')
                ->get()
                ->pluck('learner')
                ->filter();
            $candidates = $candidates->merge($byIdentifiers);
        }

        return $candidates->unique('id')->values()->toArray();
    }

    /**
     * Process deduplication for a learner: compute fingerprint, register identifiers, flag candidates.
     */
    public function processLearner(Learner $learner): array
    {
        $fingerprint = $this->computeAndStoreFingerprint($learner);

        // Register email identifier
        if ($learner->email) {
            $this->registerIdentifier($learner, 'email', $learner->email, true);
        }

        // Register phone identifier
        if ($learner->phone) {
            $this->registerIdentifier($learner, 'phone', $learner->phone);
        }

        // Find duplicates
        $duplicates = $this->findDuplicatesByFingerprint($learner);

        return [
            'fingerprint' => $fingerprint,
            'duplicate_count' => count($duplicates),
            'duplicates' => $duplicates,
        ];
    }

    /**
     * Resolve a duplicate candidate: mark as confirmed duplicate or not a duplicate.
     */
    public function resolveDuplicate(int $identifierId, string $resolution): LearnerIdentifier
    {
        $identifier = LearnerIdentifier::findOrFail($identifierId);

        $validResolutions = ['confirmed_duplicate', 'not_duplicate', 'merged'];

        if (!in_array($resolution, $validResolutions)) {
            throw new \InvalidArgumentException("Invalid resolution: {$resolution}. Must be one of: " . implode(', ', $validResolutions));
        }

        $identifier->update([
            'duplicate_status' => $resolution,
            'is_duplicate_candidate' => $resolution === 'confirmed_duplicate',
        ]);

        return $identifier;
    }
}
