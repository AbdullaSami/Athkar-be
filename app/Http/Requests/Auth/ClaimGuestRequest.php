<?php

namespace App\Http\Requests\Auth;

/** Input: unchanged guest credential. Output: validated proof for account claiming. */
class ClaimGuestRequest extends StrictAuthRequest
{
    /** @return array<string, array<string>> Never accept a client-selected owner ID. */
    public function rules(): array
    {
        return ['guest_token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]{64}$/D']];
    }
}
