<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldPlacementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'learner_id' => $this->learner_id,
            'learner' => new LearnerResource($this->whenLoaded('learner')),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location'),
            'assigned_by' => $this->assigned_by,
            'assigned_by_user' => new UserResource($this->whenLoaded('assignedByUser')),
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
