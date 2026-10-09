<?php

namespace App\Http\Middleware;

use App\Services\GuestIdentityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Authenticate guest credentials and serialize guest access with account claiming. */
class AuthenticateGuestIdentity
{
    /** Inject the owned operation service. */
    public function __construct(private GuestIdentityService $identities) {}

    /** A claimed credential cannot authorize a request that runs after the claim. */
    public function handle(Request $request, Closure $next): Response
    {
        return DB::transaction(function () use ($request, $next): Response {
            $identity = $this->identities->authenticate($request->header('X-Guest-Token'), lock: true);
            $request->attributes->set('worship_identity', $identity);
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        });
    }
}
