<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Return only the account fields intended for its authenticated owner. */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> Public account response; credentials are never included. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'age' => $this->age, 'phone' => $this->phone,
            'account_status' => $this->account_status->value,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }
}
