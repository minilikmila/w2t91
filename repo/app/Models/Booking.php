<?php

namespace App\Models;

use App\Models\Traits\AuditsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes, AuditsTimestamps;

    public const STATUS_PROVISIONAL = 'provisional';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LATE_CANCEL = 'late_cancel';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'resource_id',
        'learner_id',
        'booked_by',
        'idempotency_key',
        'status',
        'start_time',
        'end_time',
        'version',
        'hold_expires_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'version' => 'integer',
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

    public function bookedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function isProvisional(): bool
    {
        return $this->status === self::STATUS_PROVISIONAL;
    }

    public function isHoldExpired(): bool
    {
        return $this->isProvisional()
            && $this->hold_expires_at
            && $this->hold_expires_at->isPast();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PROVISIONAL, self::STATUS_CONFIRMED]);
    }
}
