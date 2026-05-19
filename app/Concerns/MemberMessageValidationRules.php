<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait MemberMessageValidationRules
{
    /**
     * Get the validation rules used to validate member messages.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function memberMessageRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
