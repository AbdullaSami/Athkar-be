<?php

namespace App\Http\Middleware;

use App\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reject suspended accounts on every authenticated API request. */
class EnsureActiveAccount
{
    /** Input: authenticated user. Output: private response or HTTP 403. */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->account_status === AccountStatus::Active, 403);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
