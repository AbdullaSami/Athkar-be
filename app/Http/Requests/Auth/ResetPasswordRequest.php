<?php

namespace App\Http\Requests\Auth;

/** Input: email, unchanged reset token and confirmed new password. */
class ResetPasswordRequest extends ForgotPasswordRequest
{
    /** @return array<string, array<mixed>> Match registration password requirements. */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
            'password' => [...$this->passwordRules(), 'min:12', 'confirmed'],
            'password_confirmation' => $this->passwordRules(),
        ];
    }
}
