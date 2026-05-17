<?php

namespace Database\Factories;

use App\Models\LivestreamEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LivestreamEvent>
 */
class LivestreamEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => LivestreamEvent::EVENT_STATUS,
            'is_live' => true,
            'title' => $this->faker->sentence(),
            'category' => $this->faker->word(),
            'viewer_count' => $this->faker->numberBetween(0, 5000),
            'payload' => [],
            'occurred_at' => now(),
        ];
    }
}
