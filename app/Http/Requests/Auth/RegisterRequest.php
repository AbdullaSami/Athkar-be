<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rule;

/** Input: account details and mobile device name. Output: validated registration fields. */
class RegisterRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Supported account registration fields. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
            'age' => ['required', 'integer', 'min:1', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'password' => [...$this->passwordRules(), 'min:12', 'confirmed'],
            'password_confirmation' => $this->passwordRules(),
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
