<?php

namespace Database\Factories;

use App\Models\WikiEntry;
use App\Services\Kick\WikiText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WikiEntry>
 */
class WikiEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = $this->faker->unique()->words(2, true);

        return [
            'type' => WikiEntry::TYPE_PERK,
            'name_en' => ucwords($nameEn),
            'name_tr' => null,
            'slug' => WikiText::slug(WikiEntry::TYPE_PERK, null, $nameEn),
            'owner' => null,
            'role' => null,
            'description_tr' => $this->faker->sentence(),
            'description_en' => $this->faker->sentence(),
            'is_enabled' => true,
            'is_curated' => false,
            'source_url' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }

    public function curated(): static
    {
        return $this->state(fn () => ['is_curated' => true]);
    }

    public function perk(string $owner, string $role): static
    {
        return $this->state(fn () => [
            'type' => WikiEntry::TYPE_PERK,
            'owner' => $owner,
            'role' => $role,
        ]);
    }
}
