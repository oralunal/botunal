<?php

namespace Database\Factories;

use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardRedemption>
 */
class RewardRedemptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kick_redemption_id' => (string) Str::uuid(),
            'reward_title' => $this->faker->words(2, true),
            'reward_cost' => $this->faker->numberBetween(100, 10000),
            'redeemer_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'redeemer_username' => $this->faker->userName(),
            'user_input' => $this->faker->optional()->sentence(),
            'status' => 'pending',
            'redeemed_at' => now(),
        ];
    }
}
