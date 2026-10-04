<?php

namespace App\Http\Requests\Auth;

/** Input: email and unchanged password. Output: validated credentials. */
class LoginRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Browser login accepts credentials only. */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => $this->passwordRules(),
        ];
    }
}
