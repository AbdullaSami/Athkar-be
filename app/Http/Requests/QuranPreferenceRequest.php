<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Services\QuranService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validate only supported owned Quran selections, without contacting the provider. */
class QuranPreferenceRequest extends StrictAuthRequest
{
    /** Only authenticated accounts may access their preferences. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> Null clears a selection; reads accept no fields. */
    public function rules(): array
    {
        if ($this->isMethod('GET') || $this->isMethod('HEAD')) {
            return [];
        }

        return [
            'translation_edition' => ['sometimes', 'nullable', 'string', 'max:100', Rule::in(QuranService::TRANSLATION_EDITIONS)],
            'reciter_identifier' => ['sometimes', 'nullable', 'string', 'max:100', Rule::in(QuranService::RECITERS)],
        ];
    }

    /** Reject empty updates while allowing explicit null resets. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->isMethod('PATCH') && ! $this->hasAny(array_keys($this->rules()))) {
                $validator->errors()->add('preferences', 'Provide at least one supported preference.');
            }
        });
    }
}
