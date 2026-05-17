<?php

namespace Database\Factories;

use App\Models\KickBan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KickBan>
 */
class KickBanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'target_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'target_username' => $this->faker->userName(),
            'moderator_username' => $this->faker->userName(),
            'action' => KickBan::ACTION_BAN,
            'reason' => $this->faker->optional()->sentence(),
            'expires_at' => null,
            'source' => KickBan::SOURCE_WEBHOOK,
            'occurred_at' => now(),
        ];
    }

    public function timeout(int $minutes = 10): static
    {
        return $this->state(fn () => [
            'action' => KickBan::ACTION_TIMEOUT,
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    public function dashboard(): static
    {
        return $this->state(fn () => ['source' => KickBan::SOURCE_DASHBOARD]);
    }
}
