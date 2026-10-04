<?php

namespace App\Http\Requests\Auth;

/** Input: credentials and device label. Output: validated mobile login fields. */
class MobileLoginRequest extends LoginRequest
{
    /** @return array<string, array<mixed>> Add the bounded mobile device label. */
    public function rules(): array
    {
        return [...parent::rules(), 'device_name' => ['required', 'string', 'max:100']];
    }
}
