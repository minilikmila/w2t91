<?php

namespace App\Models\Traits;

use App\Services\AuditService;

/**
 * Trait that records audit events on create, update, and delete.
 * Preserves prior values for versioned updates.
 */
trait AuditsTimestamps
{
    public static function bootAuditsTimestamps(): void
    {
        static::created(function ($model) {
            $model->recordAuditEvent('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            foreach (array_keys($dirty) as $key) {
                $oldValues[$key] = $model->getOriginal($key);
            }

            $model->recordAuditEvent('updated', $oldValues, $dirty);
        });

        static::deleted(function ($model) {
            $eventType = $model->isForceDeleting() ? 'force_deleted' : 'soft_deleted';
            $model->recordAuditEvent($eventType, $model->getAttributes(), null);
        });
    }

    /**
     * Record an audit event for this model.
     */
    protected function recordAuditEvent(string $eventType, ?array $oldValues, ?array $newValues): void
    {
        try {
            $auditService = app(AuditService::class);

            $actorId = null;
            if (function_exists('request') && request()?->user()) {
                $actorId = request()->user()->id;
            }

            $auditService->log(
                eventType: $eventType,
                entityType: static::class,
                entityId: $this->getKey() ?? 0,
                actorId: $actorId,
                oldValues: $oldValues,
                newValues: $newValues,
                description: "{$eventType} " . class_basename(static::class) . " #{$this->getKey()}",
                ipAddress: function_exists('request') ? request()?->ip() : null
            );
        } catch (\Exception $e) {
            // Don't let audit failures break the main operation
            report($e);
        }
    }

    /**
     * Check if this is a force delete (not soft delete).
     */
    protected function isForceDeleting(): bool
    {
        return property_exists($this, 'forceDeleting') && $this->forceDeleting;
    }
}
