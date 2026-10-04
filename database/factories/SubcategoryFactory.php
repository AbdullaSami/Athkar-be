<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subcategory> */
class SubcategoryFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'title' => fake()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
