<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearnerIdentifierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'value' => $this->value,
            'is_primary' => $this->is_primary,
            'is_duplicate_candidate' => $this->is_duplicate_candidate,
            'duplicate_of_learner_id' => $this->duplicate_of_learner_id,
            'duplicate_status' => $this->duplicate_status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
