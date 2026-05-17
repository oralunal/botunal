<?php

namespace Database\Factories;

use App\Models\KickFollow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KickFollow>
 */
class KickFollowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'follower_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'follower_username' => $this->faker->userName(),
            'followed_at' => now(),
        ];
    }
}
