<?php

namespace App\Http\Requests;

use App\Services\AlAdhanService;
use Illuminate\Validation\Rule;

/** Validate an explicit local date, timezone and supported calculation settings. */
class PrayerTimesRequest extends QiblaRequest
{
    /** @return array<string, array<mixed>> Custom method 99 is unsupported because custom angles are not accepted. */
    public function rules(): array
    {
        return [...parent::rules(),
            'date' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],
            'calculation_method' => ['sometimes', 'required', 'integer', Rule::in(AlAdhanService::CALCULATION_METHODS)],
            'school' => ['sometimes', 'required', 'integer', Rule::in([0, 1])],
            'high_latitude_method' => ['sometimes', 'required', 'integer', Rule::in([1, 2, 3])],
            'shafaq' => ['sometimes', 'required', 'string', Rule::in(['general', 'ahmer', 'abyad'])]];
    }
}
