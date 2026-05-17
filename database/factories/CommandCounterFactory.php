<?php

namespace Database\Factories;

use App\Models\CommandCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandCounter>
 */
class CommandCounterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->numberBetween(0, 100),
        ];
    }
}
