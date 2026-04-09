<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'enrollment_id',
        'reviewer_id',
        'level',
        'status',
        'decision',
        'comments',
        'reason_code',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'in_review']);
    }

    public function isDecided(): bool
    {
        return in_array($this->status, ['approved', 'rejected']);
    }
}
