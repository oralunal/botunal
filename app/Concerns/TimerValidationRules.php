<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait TimerValidationRules
{
    /**
     * Validation rules for creating/updating a timer.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function timerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            'interval_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'min_messages_between' => ['required', 'integer', 'min:0', 'max:10000'],
            'only_when_live' => ['boolean'],
            'is_enabled' => ['boolean'],
        ];
    }
}
