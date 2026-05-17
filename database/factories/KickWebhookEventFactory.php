<?php

namespace Database\Factories;

use App\Models\KickWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KickWebhookEvent>
 */
class KickWebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => (string) Str::ulid(),
            'event_type' => 'chat.message.sent',
            'event_version' => 1,
            'kick_timestamp' => now(),
            'payload' => [],
            'headers' => [],
            'processed_at' => null,
            'process_error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ofType(string $eventType, array $payload = []): static
    {
        return $this->state(fn () => [
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn () => ['processed_at' => now()]);
    }
}
