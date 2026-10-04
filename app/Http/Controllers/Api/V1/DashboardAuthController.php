<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmptyAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Coordinate the dashboard's CSRF-protected browser session authentication. */
class DashboardAuthController extends Controller
{
    /** Inject account authentication operations. */
    public function __construct(private AuthService $authService) {}

    /** Return an authorized administrator after regenerating their session. */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->loginDashboard($request->validated(), $request);

        return response()->json(['data' => (new UserResource($user))->resolve($request)])
            ->header('Cache-Control', 'no-store');
    }

    /** Return the current dashboard user's safe account representation. */
    public function me(EmptyAuthRequest $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Invalidate the current browser session and return HTTP 204. */
    public function logout(EmptyAuthRequest $request): Response
    {
        $this->authService->logoutDashboard($request);

        return response()->noContent();
    }
}
