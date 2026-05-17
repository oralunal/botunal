<?php

namespace App\Services\Kick\Contracts;

use App\Services\Kick\CommandContext;

interface BuiltInCommand
{
    /**
     * Produce the chat response for this built-in command.
     */
    public function handle(CommandContext $context): string;
}
