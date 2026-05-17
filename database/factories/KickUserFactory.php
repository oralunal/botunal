<?php

namespace Database\Factories;

use App\Models\KickUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KickUser>
 */
class KickUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seenAt = now();

        return [
            'kick_user_id' => $this->faker->unique()->numberBetween(1000, 9999999),
            'username' => $this->faker->userName(),
            'identity' => ['badges' => []],
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
        ];
    }

    /**
     * A legacy user we only ever saw without a Kick user id.
     */
    public function withoutKickId(): static
    {
        return $this->state(fn () => ['kick_user_id' => null]);
    }
}
