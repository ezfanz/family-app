<?php

namespace Database\Factories;

use App\Models\Day;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Day>
 */
class DayFactory extends Factory
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
            'day_number' => fake()->unique()->numberBetween(1, 60),
            'date' => fake()->date(),
            'city' => fake()->randomElement(Day::CITIES),
            'title' => fake()->words(3, true),
        ];
    }
}
