<?php

namespace App\Http\Requests\Auth;

/** Profile and revocation endpoints accept no body or query parameters. */
class EmptyAuthRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> No client-supplied fields are supported. */
    public function rules(): array
    {
        return [];
    }
}
