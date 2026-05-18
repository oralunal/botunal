<?php

namespace Database\Factories;

use App\Models\WikiAlias;
use App\Models\WikiEntry;
use App\Services\Kick\WikiText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WikiAlias>
 */
class WikiAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = $this->faker->unique()->word();

        return [
            'wiki_entry_id' => WikiEntry::factory(),
            'alias' => $alias,
            'alias_norm' => WikiText::normalize($alias),
        ];
    }
}
