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
    /** Inject the existing guest credential lifecycle. */
    public function __construct(private GuestIdentityService $guestIdentities) {}

    /**
     * @param  array{name: string, email: string, password: string, age: int|string, phone?: ?string, device_name: string, guest_token?: string}  $data
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

            if (isset($data['guest_token'])) {
                $this->guestIdentities->claim($user, $data['guest_token']);
            } else {
                $identity = $user->worshipIdentities()->make();
                $identity->public_id = (string) Str::uuid();
                $identity->save();
            }

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
     * @param  array{id_token: string, device_name: string, age?: int|string, guest_token?: string}  $data
     * @return array{user: User, token: string, expires_at: string} Authenticate by verified subject, never by matching email alone.
     */
    public function loginGoogle(#[\SensitiveParameter] array $data, GoogleIdTokenService $google): array
    {
        $claims = $google->verify($data['id_token']);

        try {
            return DB::transaction(function () use ($claims, $data): array {
                $user = User::query()->where('google_id', $claims['sub'])->lockForUpdate()->first();

                if ($user === null) {
                    // Email ownership alone cannot attach Google to an existing account.
                    if (User::query()->where('email', $claims['email'])->exists()) {
                        throw ValidationException::withMessages(['id_token' => 'Unable to sign in with this Google account.']);
                    }
                    if (! isset($data['age'])) {
                        throw ValidationException::withMessages(['age' => 'Age is required to create an account.']);
                    }

                    $user = new User;
                    $user->name = $claims['name'];
                    $user->email = $claims['email'];
                    $user->google_id = $claims['sub'];
                    $user->age = $data['age'];
                    $user->email_verified_at = $claims['authoritative_email'] ? now() : null;
                    $user->save();
                    $user->refresh();
                }

                if ($user->account_status !== AccountStatus::Active) {
                    throw ValidationException::withMessages(['id_token' => 'Unable to sign in with this Google account.']);
                }

                if (isset($data['guest_token'])) {
                    $this->guestIdentities->claim($user, $data['guest_token']);
                } elseif (! $user->worshipIdentities()->exists()) {
                    $identity = $user->worshipIdentities()->make();
                    $identity->public_id = (string) Str::uuid();
                    $identity->save();
                }

                return $this->issueMobileToken($user->fresh(), $data['device_name']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['id_token' => 'Unable to sign in with this Google account.']);
        }
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
        $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()));

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
