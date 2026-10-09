<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;

/** Validate public browsing input; route IDs are constrained before database lookup. */
class ContentReadRequest extends StrictAuthRequest
{
    /** @return array<string, array<string>> Details accept no query fields; lists accept bounded pagination only. */
    public function rules(): array
    {
        return $this->route()->getActionMethod() === 'index' ? [
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ] : [];
    }
}
