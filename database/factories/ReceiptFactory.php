<?php

namespace Database\Factories;

use App\Enums\ReceiptStage;
use App\Models\Receipt;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
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
            'user_id' => User::factory(),
            'store_name' => fake()->randomElement(['MUJI', 'UNIQLO', 'Don Quijote', 'Matsumoto Kiyoshi', 'Bic Camera']),
            'branch' => fake()->randomElement(['Namba', 'Shinsaibashi', 'Shinjuku', 'Dotonbori']),
            'purchased_at' => '2026-11-22 03:00:00',
            'pre_tax_yen' => fake()->numberBetween(10, 120) * 100,
            'tax_rate' => 10,
            'stage' => ReceiptStage::Checked,
        ];
    }

    /**
     * A photo saved before the amount was entered.
     */
    public function captured(): static
    {
        return $this->state(fn (): array => ['stage' => ReceiptStage::Captured, 'pre_tax_yen' => null, 'store_name' => null]);
    }
}
