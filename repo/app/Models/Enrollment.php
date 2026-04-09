<?php

namespace App\Models;

use App\Models\Traits\AuditsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes, AuditsTimestamps;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ENROLLED = 'enrolled';
    public const STATUS_WAITLISTED = 'waitlisted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';

    /**
     * Valid state transitions: current_status => [allowed next statuses]
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING_REVIEW],
        self::STATUS_PENDING_REVIEW => [self::STATUS_IN_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_IN_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_ENROLLED, self::STATUS_WAITLISTED, self::STATUS_CANCELLED],
        self::STATUS_REJECTED => [self::STATUS_DRAFT],
        self::STATUS_ENROLLED => [self::STATUS_CANCELLED, self::STATUS_COMPLETED],
        self::STATUS_WAITLISTED => [self::STATUS_ENROLLED, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [self::STATUS_REFUNDED],
        self::STATUS_COMPLETED => [],
        self::STATUS_REFUNDED => [],
    ];

    protected $fillable = [
        'learner_id',
        'program_name',
        'status',
        'previous_status',
        'current_approval_level',
        'max_approval_levels',
        'workflow_metadata',
        'requires_guardian_approval',
        'reason_code',
        'notes',
        'payment_amount',
        'payment_received',
        'refund_cutoff_at',
        'enrolled_at',
        'completed_at',
        'cancelled_at',
        'refunded_at',
        'last_actor_id',
    ];

    protected function casts(): array
    {
        return [
            'workflow_metadata' => 'array',
            'requires_guardian_approval' => 'boolean',
            'payment_amount' => 'decimal:2',
            'payment_received' => 'boolean',
            'refund_cutoff_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function lastActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_actor_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }

    public function getAllowedTransitions(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }
}
