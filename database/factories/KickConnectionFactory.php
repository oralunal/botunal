<?php

namespace Database\Factories;

use App\Models\KickConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KickConnection>
 */
class KickConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => KickConnection::TYPE_CHANNEL,
            'kick_user_id' => $this->faker->unique()->numberBetween(1000, 9999999),
            'slug' => $this->faker->userName(),
            'display_name' => $this->faker->userName(),
            'broadcaster_user_id' => $this->faker->numberBetween(1000, 9999999),
            'access_token' => 'access-'.$this->faker->sha256(),
            'refresh_token' => 'refresh-'.$this->faker->sha256(),
            'scopes' => ['user:read'],
            'token_type' => 'Bearer',
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
            'last_refreshed_at' => now(),
        ];
    }

    public function channel(): static
    {
        return $this->state(fn () => [
            'type' => KickConnection::TYPE_CHANNEL,
            'scopes' => [
                'events:subscribe', 'moderation:ban', 'moderation:chat_message:manage',
                'channel:read', 'user:read', 'channel:rewards:read', 'kicks:read',
            ],
        ]);
    }

    public function bot(): static
    {
        return $this->state(fn () => [
            'type' => KickConnection::TYPE_BOT,
            'broadcaster_user_id' => null,
            'scopes' => ['chat:write', 'user:read'],
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinutes(5)]);
    }
}
