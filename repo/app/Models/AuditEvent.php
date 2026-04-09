<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'actor_id',
        'actor_type',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'prior_hash',
        'event_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Audit events are append-only. Prevent updates and deletes.
     */
    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('Audit events are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit events are immutable and cannot be deleted.');
        });
    }
}
