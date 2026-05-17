<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kick_message_id' => (string) Str::uuid(),
            'sender_kick_user_id' => $this->faker->numberBetween(1000, 9999999),
            'sender_username' => $this->faker->userName(),
            'sender_identity' => ['badges' => []],
            'content' => $this->faker->sentence(),
            'is_command' => false,
            'replied_to_message_id' => null,
            'deleted_at' => null,
            'sent_at' => now(),
        ];
    }

    public function command(string $content = '!discord'): static
    {
        return $this->state(fn () => [
            'content' => $content,
            'is_command' => true,
        ]);
    }
}
