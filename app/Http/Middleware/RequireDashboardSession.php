<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Dashboard endpoints accept a first-party session rather than mobile tokens. */
class RequireDashboardSession
{
    /** Input: Sanctum-authenticated request. Output: session-authenticated request or HTTP 401. */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
