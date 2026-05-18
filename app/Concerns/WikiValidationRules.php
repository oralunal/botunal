<?php

namespace App\Concerns;

use App\Models\WikiEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait WikiValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function wikiRules(): array
    {
        return [
            'type' => ['required', Rule::in(WikiEntry::TYPES)],
            'name_en' => ['required', 'string', 'max:191'],
            'name_tr' => ['nullable', 'string', 'max:191'],
            'owner' => ['nullable', 'string', 'max:191'],
            'role' => ['nullable', Rule::in([WikiEntry::ROLE_SURVIVOR, WikiEntry::ROLE_KILLER])],
            'description_tr' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['nullable', 'string', 'max:255', 'url'],
            'is_enabled' => ['boolean'],
            'aliases' => ['array'],
            'aliases.*' => ['string', 'max:100', 'distinct'],
        ];
    }
}
