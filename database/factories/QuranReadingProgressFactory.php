<?php

namespace Database\Factories;

use App\Models\QuranReadingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuranReadingProgress> */
class QuranReadingProgressFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'surah_number' => 1,
            'ayah_number' => 1,
            'last_read_at' => now(),
        ];
    }
}
