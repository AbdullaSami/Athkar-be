<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WorshipIdentity> */
class WorshipIdentityFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'user_id' => null,
            'guest_token_hash' => hash('sha256', Str::random(64)),
        ];
    }

    /** Associate this identity with an account and revoke its guest credential. */
    public function registered(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user ?? User::factory(), 'guest_token_hash' => null]);
    }
}
