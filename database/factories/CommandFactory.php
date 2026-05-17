<?php

namespace Database\Factories;

use App\Models\Command;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Command>
 */
class CommandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'type' => Command::TYPE_STATIC,
            'handler' => null,
            'response' => 'Hello {user}!',
            'permission' => Command::PERMISSION_EVERYONE,
            'cooldown_seconds' => 0,
            'user_cooldown_seconds' => 0,
            'is_enabled' => true,
            'reply_in_thread' => false,
            'usage_count' => 0,
            'last_used_at' => null,
            'created_by' => null,
        ];
    }

    public function dynamic(string $handler): static
    {
        return $this->state(fn () => [
            'type' => Command::TYPE_DYNAMIC,
            'handler' => $handler,
            'response' => null,
        ]);
    }

    public function permission(string $permission): static
    {
        return $this->state(fn () => ['permission' => $permission]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
