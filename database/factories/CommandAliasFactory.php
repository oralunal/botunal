<?php

namespace Database\Factories;

use App\Models\Command;
use App\Models\CommandAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandAlias>
 */
class CommandAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'command_id' => Command::factory(),
            'alias' => $this->faker->unique()->word(),
        ];
    }
}
