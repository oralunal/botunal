<?php

namespace Database\Factories;

use App\Models\KickGift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KickGift>
 */
class KickGiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'sender_username' => $this->faker->userName(),
            'recipient_username' => $this->faker->userName(),
            'gift_name' => $this->faker->randomElement(['Rocket', 'Heart', 'Star']),
            'kicks_amount' => $this->faker->numberBetween(1, 1000),
            'message' => $this->faker->optional()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
