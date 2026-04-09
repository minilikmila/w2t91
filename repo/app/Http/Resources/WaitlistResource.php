<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_id' => $this->resource_id,
            'learner_id' => $this->learner_id,
            'learner' => new LearnerResource($this->whenLoaded('learner')),
            'resource' => $this->whenLoaded('resource'),
            'desired_start_time' => $this->desired_start_time?->toIso8601String(),
            'desired_end_time' => $this->desired_end_time?->toIso8601String(),
            'status' => $this->status,
            'position' => $this->position,
            'offered_at' => $this->offered_at?->toIso8601String(),
            'offer_expires_at' => $this->offer_expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
