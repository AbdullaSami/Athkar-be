<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Reject markup and null bytes without mutating Arabic text or meaningful whitespace. */
class PlainContentText implements ValidationRule
{
    /** Input: a textual content field. Output: a validation error for unsupported executable formatting. */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && (str_contains($value, "\0") || preg_match('/<\s*[a-z!\/?]/i', $value))) {
            $fail('Use plain text without HTML markup or null bytes.');
        }
    }
}
