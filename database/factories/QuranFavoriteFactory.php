<?php

namespace Database\Factories;

use App\Models\QuranFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuranFavorite> */
class QuranFavoriteFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'surah_number' => 1,
            'ayah_number' => 1,
        ];
    }
}
