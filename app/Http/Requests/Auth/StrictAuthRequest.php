<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/** Normalize account input and reject keys outside the endpoint contract. */
abstract class StrictAuthRequest extends FormRequest
{
    /** Public authentication and already protected routes validate input here. */
    public function authorize(): bool
    {
        return true;
    }

    /** Normalize email only; passwords and tokens keep their original bytes. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** Reject unsupported body and query keys without reflecting their values. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Unsupported field.');
            }
        });
    }

    /** @return array<int, string|Closure> Validate password bytes without mutating them. */
    protected function passwordRules(): array
    {
        return [
            'required', 'string', 'max:128',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (str_contains($value, "\0") || (config('hashing.driver') === 'bcrypt' && strlen($value) > 72))) {
                    $fail('The password contains unsupported characters or exceeds the supported byte length.');
                }
            },
        ];
    }
}
