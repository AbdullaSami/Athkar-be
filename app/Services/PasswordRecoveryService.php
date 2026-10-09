<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\User;
use App\Notifications\ResetAccountPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/** Use Laravel's password broker with generic responses and atomic credential revocation. */
class PasswordRecoveryService
{
    /** Send an eligible account a reset link without exposing its existence or mail failures. */
    public function sendLink(string $email): void
    {
        $this->ensureDeliveryConfigured();

        try {
            Password::broker()->sendResetLink([
                'email' => $email,
                'account_status' => AccountStatus::Active->value,
                'local_account' => fn (Builder $query): Builder => $query->whereNotNull('password'),
            ], function (User $user, #[\SensitiveParameter] string $token): void {
                $user->notify(new ResetAccountPassword($token));
            });
        } catch (Throwable $exception) {
            // Transport exception messages may contain credential-bearing URLs.
            Log::warning('Password recovery delivery failed.', ['exception_type' => $exception::class]);
        }
    }

    /**
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $data
     *                                                                                                      Change a local account password once, then revoke its existing credentials.
     */
    public function reset(#[\SensitiveParameter] array $data): void
    {
        DB::transaction(function () use ($data): void {
            $user = User::query()->where('email', $data['email'])->lockForUpdate()->first();
            if (! $user || $user->account_status !== AccountStatus::Active || $user->password === null) {
                $this->invalidReset();
            }

            $status = Password::broker()->reset($data, function (User $user, #[\SensitiveParameter] string $password): void {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();

                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                        ->where('user_id', $user->id)->delete();
                }

                DB::afterCommit(fn () => event(new PasswordReset($user)));
            });

            if ($status !== Password::PASSWORD_RESET) {
                $this->invalidReset();
            }
        });
    }

    /** A single public error covers unknown accounts, expired proofs and replay attempts. */
    private function invalidReset(): never
    {
        throw ValidationException::withMessages(['token' => 'This password reset link is invalid or expired.']);
    }

    /** Fail closed before account lookup when the reset destination or mailer is unsafe. */
    private function ensureDeliveryConfigured(): void
    {
        $url = config('auth.password_reset_url');
        $parts = is_string($url) ? parse_url($url) : false;
        $mailer = config('auth.password_reset_mailer');
        $transport = is_string($mailer) ? config('mail.mailers.'.$mailer.'.transport') : null;
        $validUrl = is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts) && isset($parts['host']) && ($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment']);

        if (! $validUrl || ! in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'array'], true)) {
            throw new ServiceUnavailableHttpException;
        }
    }
}
