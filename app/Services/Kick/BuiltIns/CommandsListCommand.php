<?php

namespace App\Services\Kick\BuiltIns;

use App\Models\Command;
use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;

class CommandsListCommand implements BuiltInCommand
{
    public function handle(CommandContext $context): string
    {
        $prefix = (string) config('services.kick.command_prefix', '!');

        $names = Command::query()
            ->enabled()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name): string => $prefix.$name)
            ->all();

        return $names === []
            ? 'No commands available.'
            : 'Commands: '.implode(', ', $names);
    }
}
