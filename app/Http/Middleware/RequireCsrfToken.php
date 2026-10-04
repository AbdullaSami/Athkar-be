<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/** Keep Laravel CSRF protection while requiring tokens even for same-origin requests. */
class RequireCsrfToken extends PreventRequestForgery
{
    /** Origin metadata alone must not replace the dashboard's CSRF token. */
    protected function hasValidOrigin(mixed $request): bool
    {
        return false;
    }
}
