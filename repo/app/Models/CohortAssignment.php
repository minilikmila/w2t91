<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CohortAssignment extends Model
{
    protected $fillable = [
        'cohort_id',
        'security_exercise_id',
        'learner_id',
        'assigned_at',
        'due_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(SecurityExercise::class, 'security_exercise_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
