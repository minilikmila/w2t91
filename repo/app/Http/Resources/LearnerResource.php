<?php

namespace App\Http\Resources;

use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $encryptionService = app(EncryptionService::class);

        // Determine if the requesting user can see unmasked PII
        $canViewFullPii = $this->canViewFullPii($request);

        $email = $this->resource->decryptField('email');
        $phone = $this->resource->decryptField('phone');
        $guardianContact = $this->resource->decryptField('guardian_contact');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'email' => $canViewFullPii ? $email : $encryptionService->maskEmail($email),
            'phone' => $canViewFullPii ? $phone : $encryptionService->maskPhone($phone),
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'language' => $this->language,
            'address' => $this->address,
            'guardian_name' => $this->guardian_name,
            'guardian_contact' => $canViewFullPii ? $guardianContact : $encryptionService->maskPhone($guardianContact),
            'status' => $this->status,
            'is_minor' => $this->isMinor(),
            'fingerprint' => $this->fingerprint,
            'metadata' => $this->metadata,
            'identifiers' => LearnerIdentifierResource::collection($this->whenLoaded('identifiers')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function canViewFullPii(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // Admin and planner roles can see full PII
        return $user->hasRole('admin') || $user->hasRole('planner');
    }
}
