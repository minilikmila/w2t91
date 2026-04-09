<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'resource_id',
        'learner_id',
        'desired_start_time',
        'desired_end_time',
        'status',
        'offered_at',
        'offer_expires_at',
        'accepted_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'desired_start_time' => 'datetime',
            'desired_end_time' => 'datetime',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function isOfferExpired(): bool
    {
        return $this->status === self::STATUS_OFFERED
            && $this->offer_expires_at
            && $this->offer_expires_at->isPast();
    }
}
