<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use Illuminate\Validation\Rule;

/** A Gregorian month maps to at most 31 Hijri calendar entries. */
class IslamicCalendarRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Bound the calendar range and accepted calculation methods. */
    public function rules(): array
    {
        return ['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12'],
            'calendar_method' => ['sometimes', 'required', 'string', Rule::in(['HJCoSA', 'UAQ', 'DIYANET', 'MATHEMATICAL'])]];
    }
}
