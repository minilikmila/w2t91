<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'learner_id' => $this->learner_id,
            'learner' => new LearnerResource($this->whenLoaded('learner')),
            'program_name' => $this->program_name,
            'status' => $this->status,
            'previous_status' => $this->previous_status,
            'current_approval_level' => $this->current_approval_level,
            'max_approval_levels' => $this->max_approval_levels,
            'requires_guardian_approval' => $this->requires_guardian_approval,
            'reason_code' => $this->reason_code,
            'notes' => $this->notes,
            'payment_amount' => $this->payment_amount,
            'payment_received' => $this->payment_received,
            'refund_cutoff_at' => $this->refund_cutoff_at?->toIso8601String(),
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'last_actor_id' => $this->last_actor_id,
            'last_actor' => new UserResource($this->whenLoaded('lastActor')),
            'approvals' => $this->whenLoaded('approvals'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
