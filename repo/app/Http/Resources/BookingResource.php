<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_id' => $this->resource_id,
            'learner_id' => $this->learner_id,
            'learner' => new LearnerResource($this->whenLoaded('learner')),
            'resource' => $this->whenLoaded('resource'),
            'booked_by' => $this->booked_by,
            'booked_by_user' => new UserResource($this->whenLoaded('bookedByUser')),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'idempotency_key' => $this->idempotency_key,
            'version' => $this->version,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at,
            'cancelled_at' => $this->cancelled_at,
            'late_cancel' => $this->late_cancel,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
