<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose minimal account overview fields to authorized administrators. */
class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> Omit credentials, contact phone, age and private worship data. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_status' => $this->account_status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
