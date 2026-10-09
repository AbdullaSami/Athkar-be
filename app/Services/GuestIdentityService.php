<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Issue anonymous credentials and transfer identities only with proof of possession. */
class GuestIdentityService
{
    /** @return array{identity_id: string, guest_token: string} Return the credential once; store its hash only. */
    public function create(): array
    {
        $token = Str::random(64);
        $identity = new WorshipIdentity;
        $identity->public_id = (string) Str::uuid();
        $identity->guest_token_hash = hash('sha256', $token);
        $identity->save();

        return ['identity_id' => $identity->public_id, 'guest_token' => $token];
    }

    /** Authenticate an unclaimed identity; locking callers must hold a database transaction. */
    public function authenticate(#[\SensitiveParameter] mixed $token, bool $lock = false): WorshipIdentity
    {
        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{64}$/D', $token) !== 1) {
            throw new AuthenticationException;
        }

        $query = WorshipIdentity::query()->whereNull('user_id')->where('guest_token_hash', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? throw new AuthenticationException;
    }

    /** Associate guest Wird with an account atomically and permanently revoke the guest credential. */
    public function claim(User $user, #[\SensitiveParameter] string $token): void
    {
        DB::transaction(function () use ($user, $token): void {
            $identity = $this->authenticate($token, lock: true);
            $identity->user()->associate($user);
            $identity->guest_token_hash = null;
            $identity->save();
        });
    }
}
