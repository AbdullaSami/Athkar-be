<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serialize only role identifiers, names and eagerly loaded permission names. */
class AdminRoleResource extends JsonResource
{
    /** @return array{id: int, name: string, permissions: array<int, string>} Authorized role representation. */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'permissions' => $this->permissions->pluck('name')->sort()->values()->all()];
    }
}
