<?php

namespace Database\Factories;

use App\Models\Timer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timer>
 */
class TimerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'message' => 'Follow the channel! {channel}',
            'interval_seconds' => 600,
            'min_messages_between' => 0,
            'only_when_live' => true,
            'is_enabled' => true,
            'last_run_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
