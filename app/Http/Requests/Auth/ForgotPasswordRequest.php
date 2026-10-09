<?php

namespace App\Http\Requests\Auth;

/** Input: normalized email. Output: validated password-recovery identity. */
class ForgotPasswordRequest extends StrictAuthRequest
{
    /** @return array<string, array<string>> Do not validate account existence publicly. */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:254']];
    }
}
