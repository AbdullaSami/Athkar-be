<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose minimal account overview fields to authorized administrators. */
class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> Detail responses include profile fields and loaded roles; credentials and worship data stay private. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_status' => $this->account_status->value,
            'age' => $this->whenHas('age'),
            'phone' => $this->whenHas('phone'),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role): array => ['id' => $role->id, 'name' => $role->name])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
