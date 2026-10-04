<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Register accounts and keep mobile tokens separate from dashboard sessions. */
class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string, age: int|string, phone?: ?string, device_name: string}  $data
     * @return array{user: User, token: string, expires_at: string}
     */
    public function registerMobile(#[\SensitiveParameter] array $data): array
    {
        return DB::transaction(function () use ($data): array {
            try {
                $user = User::create([
                    'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                    'age' => $data['age'], 'phone' => $data['phone'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
            }

            $identity = $user->worshipIdentities()->make();
            $identity->public_id = (string) Str::uuid();
            $identity->save();

            return $this->issueMobileToken($user->fresh(), $data['device_name']);
        });
    }

    /**
     * @param  array{email: string, password: string, device_name: string}  $data
     * @return array{user: User, token: string, expires_at: string}
     */
    public function loginMobile(#[\SensitiveParameter] array $data): array
    {
        return $this->issueMobileToken($this->validateCredentials($data), $data['device_name']);
    }

    /**
     * @param  array{email: string, password: string}  $data
     *                                                        Output: authorized dashboard user with a regenerated session.
     */
    public function loginDashboard(#[\SensitiveParameter] array $data, Request $request): User
    {
        $user = $this->validateCredentials($data);

        if (! $user->can('access-dashboard')) {
            throw new AccessDeniedHttpException('Dashboard access is forbidden.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $user;
    }

    /** Invalidate this browser session and regenerate its CSRF token. */
    public function logoutDashboard(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** Revoke only the presented mobile credential, or all of this user's tokens. */
    public function revokeMobile(User $user, bool $all = false): void
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            throw new AuthenticationException;
        }

        if ($all) {
            $user->tokens()->delete();
        } else {
            $token->delete();
        }
    }

    /**
     * @param  array{email: string, password: string, device_name?: string}  $data
     *                                                                              Output: active user; invalid, Google-only and suspended accounts receive one generic error.
     */
    private function validateCredentials(#[\SensitiveParameter] array $data): User
    {
        $guard = Auth::guard('web');

        if (! $guard->validate(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        $user = $guard->getLastAttempted();

        if ($user->account_status !== AccountStatus::Active) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        return $user;
    }

    /** @return array{user: User, token: string, expires_at: string} Issue a bounded mobile-only token. */
    private function issueMobileToken(User $user, string $deviceName): array
    {
        $expiresAt = now()->addMinutes(max(1, (int) config('sanctum.expiration', 43200)));
        $token = $user->createToken($deviceName, ['mobile'], $expiresAt);

        return ['user' => $user, 'token' => $token->plainTextToken, 'expires_at' => $expiresAt->toIso8601String()];
    }
}
