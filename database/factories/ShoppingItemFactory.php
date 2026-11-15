<?php

namespace Database\Factories;

use App\Models\ShoppingItem;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingItem>
 */
class ShoppingItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'name' => fake()->words(3, true),
            'qty' => fake()->numberBetween(1, 4),
            'estimated_yen' => fake()->numberBetween(1, 100) * 100,
        ];
    }
}
