<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;

/** Coordinate validated recovery requests without returning account or token details. */
class PasswordRecoveryController extends Controller
{
    /** Inject broker-backed recovery operations. */
    public function __construct(private PasswordRecoveryService $recovery) {}

    /** Return the same accepted response for every valid email. */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->recovery->sendLink($request->validated('email'));

        return response()->json(['message' => 'If the account is eligible, password reset instructions will be sent.'], 202)
            ->header('Cache-Control', 'no-store');
    }

    /** Reset a proven account without creating a new token or browser session. */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->recovery->reset($request->validated());

        return response()->json(['message' => 'Password reset successfully. Please sign in again.'])
            ->header('Cache-Control', 'no-store');
    }
}
