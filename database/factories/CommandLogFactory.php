<?php

namespace Database\Factories;

use App\Models\CommandLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandLog>
 */
class CommandLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'command_id' => null,
            'alias_used' => null,
            'invoker_username' => $this->faker->userName(),
            'invoker_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'raw_message' => '!discord',
            'response_sent' => 'discord.gg/example',
            'outcome' => CommandLog::OUTCOME_SENT,
            'occurred_at' => now(),
        ];
    }
}
