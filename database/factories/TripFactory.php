<?php

namespace Database\Factories;

use App\Enums\TripRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'name' => 'Trip to '.fake()->city(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+6 days')->format('Y-m-d'),
            'timezone' => 'Asia/Tokyo',
            'exchange_rate_rm_per_100_yen' => 3.00,
            'deposit_rm' => 0,
        ];
    }

    /**
     * Attach the given user to the trip with a role.
     */
    public function withMember(User $user, TripRole $role): static
    {
        return $this->afterCreating(fn (Trip $trip) => $trip->members()->attach($user, ['role' => $role->value]));
    }
}
