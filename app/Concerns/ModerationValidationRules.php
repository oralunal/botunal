<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ModerationValidationRules
{
    /**
     * Validation rules for a ban/timeout action.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function banRules(): array
    {
        return [
            'target' => ['required', 'string', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'reason' => ['nullable', 'string', 'max:100'],
        ];
    }
}
