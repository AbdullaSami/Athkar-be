<?php

namespace Database\Factories;

use App\Models\PersonalWird;
use App\Models\WorshipIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonalWird> */
class PersonalWirdFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'worship_identity_id' => WorshipIdentity::factory(),
            'text' => fake()->paragraph(),
            'target_count' => 33,
            'current_count' => 0,
        ];
    }
}
