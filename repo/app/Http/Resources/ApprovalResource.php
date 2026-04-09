<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'enrollment' => new EnrollmentResource($this->whenLoaded('enrollment')),
            'reviewer_id' => $this->reviewer_id,
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'level' => $this->level,
            'status' => $this->status,
            'decision' => $this->decision,
            'comments' => $this->comments,
            'reason_code' => $this->reason_code,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
