<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Day;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_id' => Day::factory(),
            'trip_id' => fn (array $attributes) => Day::find($attributes['day_id'])->trip_id,
            'start_time' => fake()->time('H:i'),
            'name' => fake()->sentence(3),
            'places' => [fake()->city()],
            'is_halal' => fake()->boolean(),
        ];
    }
}
