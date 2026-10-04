<?php

namespace Database\Factories;

use App\Models\Dhikr;
use App\Models\Subcategory;
use App\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dhikr> */
class DhikrFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'subcategory_id' => Subcategory::factory(),
            'title' => 'Test content — not religious reference material',
            'text' => fake()->paragraph(),
            'description' => fake()->sentence(),
            'source' => 'Test fixture only',
            'virtue' => fake()->sentence(),
            'repetition_target' => 3,
            'publication_status' => PublicationStatus::Draft,
            'sort_order' => 0,
        ];
    }

    /** Make content available to public readers. */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['publication_status' => PublicationStatus::Published]);
    }
}
