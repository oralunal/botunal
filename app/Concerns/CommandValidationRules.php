<?php

namespace App\Concerns;

use App\Models\Command;
use App\Services\Kick\BuiltInCommandRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CommandValidationRules
{
    /**
     * Validation rules for creating/updating a chat command.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function commandRules(?int $commandId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/',
                $commandId === null
                    ? Rule::unique(Command::class, 'name')
                    : Rule::unique(Command::class, 'name')->ignore($commandId),
            ],
            'type' => ['required', Rule::in([Command::TYPE_STATIC, Command::TYPE_DYNAMIC])],
            'handler' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('type') === Command::TYPE_DYNAMIC),
                Rule::in(BuiltInCommandRegistry::handlers()),
            ],
            'response' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === Command::TYPE_STATIC),
                'nullable', 'string', 'max:500',
            ],
            'permission' => ['required', Rule::in([
                Command::PERMISSION_EVERYONE,
                Command::PERMISSION_SUBSCRIBER,
                Command::PERMISSION_MODERATOR,
                Command::PERMISSION_BROADCASTER,
            ])],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'user_cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'is_enabled' => ['boolean'],
            'reply_in_thread' => ['boolean'],
            'aliases' => ['array'],
            'aliases.*' => ['string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/', 'distinct'],
        ];
    }
}
