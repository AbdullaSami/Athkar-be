<?php

namespace App\Http\Requests\Auth;

/** Input: unchanged Google ID token, device label, optional registration age and guest proof. */
class GoogleLoginRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Do not accept client-supplied identity or role claims. */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8192', 'regex:/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D'],
            'device_name' => ['required', 'string', 'max:100'],
            'age' => ['sometimes', 'required', 'integer', 'min:1', 'max:120'],
            'guest_token' => ['sometimes', 'required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]{64}$/D'],
        ];
    }
}
