<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Create deterministic development accounts without changing existing credentials or privileges. */
class SeedAccountService
{
    /** Input: fixed seed name/email. Output: existing or newly created local account with a hashed password. */
    public function account(string $name, string $email): User
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo account seeding is restricted to local and testing environments.');
        }
        $password = config('auth.seed_account_password');
        if (Validator::make(['password' => $password], ['password' => ['required', 'string', 'min:12', 'max:128']])->fails()
            || str_contains($password, "\0") || (config('hashing.driver') === 'bcrypt' && strlen($password) > 72)) {
            throw new RuntimeException('Set SEED_ACCOUNT_PASSWORD to a valid password: at least 12 characters, no null bytes, and at most 72 bytes for bcrypt.');
        }

        return User::query()->firstOrCreate(['email' => $email], [
            'name' => $name, 'age' => 25, 'phone' => null, 'password' => Hash::make($password),
        ])->refresh();
    }
}
