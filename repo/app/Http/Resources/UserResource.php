<?php

namespace App\Http\Resources;

use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewFullPii = $this->canViewFullPii($request);
        $encryptionService = app(EncryptionService::class);

        $email = $this->resource->email;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $canViewFullPii ? $email : $encryptionService->maskEmail($email),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'role' => $this->whenLoaded('role', function () {
                return [
                    'name' => $this->role->name,
                    'slug' => $this->role->slug,
                    'permissions' => $this->role->relationLoaded('permissions')
                        ? $this->role->permissions->pluck('slug')
                        : null,
                ];
            }),
        ];
    }

    private function canViewFullPii(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // Users can always see their own data
        if ($user->id === $this->id) {
            return true;
        }

        return $user->hasRole('admin') || $user->hasRole('planner');
    }
}
