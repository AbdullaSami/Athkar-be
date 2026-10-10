<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;

/** Validate bounded overview queries after explicit dashboard authorization. */
class AdminUserOverviewRequest extends StrictAuthRequest
{
    /** Require a separate permission before exposing account or guest metadata. */
    public function authorize(): bool
    {
        return $this->user()?->can('view-users') ?? false;
    }

    /** @return array<string, array<mixed>> Accept pagination only on list endpoints. */
    public function rules(): array
    {
        return $this->route()->getActionMethod() === 'show' ? [] : [
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
