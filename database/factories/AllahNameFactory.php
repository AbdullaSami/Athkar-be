<?php

namespace Database\Factories;

use App\Models\AllahName;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AllahName> */
class AllahNameFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'number' => fake()->numberBetween(1, 99),
            'arabic_name' => 'اسم تجريبي',
            'transliteration' => 'Test fixture only',
            'meaning' => 'Test fixture — not verified Islamic content',
            'language' => 'ar',
        ];
    }
}
