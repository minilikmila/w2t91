<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerIdentifier extends Model
{
    protected $fillable = [
        'learner_id',
        'type',
        'value',
        'fingerprint',
        'is_primary',
        'is_duplicate_candidate',
        'duplicate_of_learner_id',
        'duplicate_status',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_duplicate_candidate' => 'boolean',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function duplicateOfLearner(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'duplicate_of_learner_id');
    }
}
