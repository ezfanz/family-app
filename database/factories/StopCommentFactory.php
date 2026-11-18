<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\StopComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StopComment>
 */
class StopCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'mention_ids' => null,
        ];
    }
}
