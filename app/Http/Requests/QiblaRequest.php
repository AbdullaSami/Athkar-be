<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;

/** Validate location without persisting it or accepting provider URLs. */
class QiblaRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Geographic ranges; all other fields are rejected. */
    public function rules(): array
    {
        return ['latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180']];
    }
}
