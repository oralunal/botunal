<?php

namespace Database\Factories;

use App\Models\KickSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KickSubscription>
 */
class KickSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => KickSubscription::TYPE_NEW,
            'subscriber_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'subscriber_username' => $this->faker->userName(),
            'gifter_username' => null,
            'tier' => '1',
            'duration' => 1,
            'quantity' => null,
            'occurred_at' => now(),
        ];
    }

    public function gift(): static
    {
        return $this->state(fn () => [
            'type' => KickSubscription::TYPE_GIFT,
            'gifter_username' => $this->faker->userName(),
            'quantity' => $this->faker->numberBetween(1, 10),
        ]);
    }

    public function renewal(): static
    {
        return $this->state(fn () => ['type' => KickSubscription::TYPE_RENEWAL]);
    }
}
