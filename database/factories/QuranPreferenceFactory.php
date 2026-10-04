<?php

namespace Database\Factories;

use App\Models\QuranPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuranPreference> */
class QuranPreferenceFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'translation_edition' => null,
            'reciter_identifier' => null,
        ];
    }
}
