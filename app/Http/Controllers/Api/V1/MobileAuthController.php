<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmptyAuthRequest;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\MobileLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\GoogleIdTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Validate mobile authentication input and return scoped token responses. */
class MobileAuthController extends Controller
{
    /** Inject account authentication operations. */
    public function __construct(private AuthService $authService) {}

    /** Create an account and return its first mobile token with HTTP 201. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->registerMobile($request->safe()->except(['password_confirmation']));

        return response()->json(['data' => [
            'user' => (new UserResource($result['user']))->resolve($request),
            'token' => $result['token'], 'token_type' => 'Bearer', 'expires_at' => $result['expires_at'],
        ]], 201)->header('Cache-Control', 'no-store');
    }

    /** Verify credentials and return one expiring mobile token. */
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginMobile($request->validated());

        return response()->json(['data' => [
            'user' => (new UserResource($result['user']))->resolve($request),
            'token' => $result['token'], 'token_type' => 'Bearer', 'expires_at' => $result['expires_at'],
        ]])->header('Cache-Control', 'no-store');
    }

    /** Exchange a verified Google ID token for one expiring mobile credential. */
    public function google(GoogleLoginRequest $request, GoogleIdTokenService $google): JsonResponse
    {
        $result = $this->authService->loginGoogle($request->validated(), $google);

        return response()->json(['data' => [
            'user' => (new UserResource($result['user']))->resolve($request),
            'token' => $result['token'], 'token_type' => 'Bearer', 'expires_at' => $result['expires_at'],
        ]])->header('Cache-Control', 'no-store');
    }

    /** Return the authenticated owner's account fields. */
    public function me(EmptyAuthRequest $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Revoke the current token and return HTTP 204. */
    public function logout(EmptyAuthRequest $request): Response
    {
        $this->authService->revokeMobile($request->user());

        return response()->noContent();
    }

    /** Revoke all of this user's tokens and return HTTP 204. */
    public function logoutAll(EmptyAuthRequest $request): Response
    {
        $this->authService->revokeMobile($request->user(), all: true);

        return response()->noContent();
    }
}
