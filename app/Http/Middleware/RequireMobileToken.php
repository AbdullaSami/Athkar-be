<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Mobile endpoints require a presented token with the mobile ability. */
class RequireMobileToken
{
    /** Do not let a browser session substitute for a mobile Bearer token. */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $request->bearerToken() || ! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        abort_unless($token->can('mobile'), 403);

        return $next($request);
    }
}
