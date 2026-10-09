<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Services\AlAdhanService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validate only the caller's supported calculation preferences. */
class PrayerSettingRequest extends StrictAuthRequest
{
    /** Only authenticated accounts may access their own settings. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> Null resets a preference to automatic. */
    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return [];
        }

        return [
            'calculation_method' => ['sometimes', 'nullable', 'integer', Rule::in(AlAdhanService::CALCULATION_METHODS)],
            'school' => ['sometimes', 'required', 'integer', Rule::in([0, 1])],
            'high_latitude_method' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2, 3])],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64', 'timezone'],
        ];
    }

    /** Reject empty updates while preserving explicit null resets. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->isMethod('PATCH') && ! $this->hasAny(array_keys($this->rules()))) {
                $validator->errors()->add('settings', 'Provide at least one supported setting.');
            }
        });
    }
}
