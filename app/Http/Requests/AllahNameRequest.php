<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use Illuminate\Validation\Rule;

/** Validate public Asma queries; routes constrain name numbers to 1 through 99. */
class AllahNameRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Language identifies meanings; Arabic names are always returned. */
    public function rules(): array
    {
        $rules = ['language' => ['sometimes', 'required', 'string', 'max:12', Rule::in(['en'])]];
        if ($this->route()->getActionMethod() === 'index') {
            $rules += ['page' => ['sometimes', 'required', 'integer', 'between:1,99'],
                'per_page' => ['sometimes', 'required', 'integer', 'between:1,99']];
        }

        return $rules;
    }
}
