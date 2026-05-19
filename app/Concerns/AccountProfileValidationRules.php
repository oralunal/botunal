<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AccountProfileValidationRules
{
    /**
     * Get the validation rules used to validate account profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accountProfileRules(?int $userId = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $userId === null
                    ? Rule::unique(User::class)
                    : Rule::unique(User::class)->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'twitter' => ['nullable', 'string', 'max:100'],
        ];
    }
}
