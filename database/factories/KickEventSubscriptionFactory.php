<?php

namespace Database\Factories;

use App\Models\KickEventSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KickEventSubscription>
 */
class KickEventSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kick_subscription_id' => (string) Str::uuid(),
            'event_name' => 'chat.message.sent',
            'event_version' => 1,
            'method' => 'webhook',
            'broadcaster_user_id' => $this->faker->numberBetween(1000, 9999999),
            'status' => KickEventSubscription::STATUS_ACTIVE,
            'last_synced_at' => now(),
        ];
    }
}
