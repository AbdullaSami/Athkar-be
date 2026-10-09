<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClaimGuestRequest;
use App\Http\Requests\Auth\EmptyAuthRequest;
use App\Services\GuestIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Issue guest credentials and claim them for the authenticated account. */
class GuestIdentityController extends Controller
{
    /** Inject the owned operation service. */
    public function __construct(private GuestIdentityService $identities) {}

    /** Issue an anonymous identity; the mobile client must store the credential securely. */
    public function store(EmptyAuthRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->identities->create()], 201)->header('Cache-Control', 'no-store');
    }

    /** Claim the proven guest identity without accepting an owner ID. */
    public function claim(ClaimGuestRequest $request): Response
    {
        $this->identities->claim($request->user(), $request->validated('guest_token'));

        return response()->noContent();
    }
}
