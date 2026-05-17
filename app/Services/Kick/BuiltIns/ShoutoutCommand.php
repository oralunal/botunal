<?php

namespace App\Services\Kick\BuiltIns;

use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;

/**
 * Generates a shout-out message: "!so someone".
 */
class ShoutoutCommand implements BuiltInCommand
{
    public function handle(CommandContext $context): string
    {
        $target = ltrim($context->args[0] ?? '', '@');

        if ($target === '') {
            return 'Usage: '.config('services.kick.command_prefix', '!').'so <username>';
        }

        return "Go check out @{$target} at https://kick.com/{$target} — they are awesome!";
    }
}
