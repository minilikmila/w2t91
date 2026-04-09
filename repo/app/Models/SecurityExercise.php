<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityExercise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type',
        'difficulty',
        'max_score',
        'passing_score',
        'scoring_rules',
        'configuration',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'passing_score' => 'integer',
            'scoring_rules' => 'array',
            'configuration' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function cohortAssignments(): HasMany
    {
        return $this->hasMany(CohortAssignment::class);
    }
}
