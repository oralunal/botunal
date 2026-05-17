<?php

namespace App\Services\Kick\BuiltIns;

use App\Models\CommandCounter;
use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;

/**
 * Increments and reports a named counter, e.g. "!deaths" => "Deaths: 5".
 */
class CounterCommand implements BuiltInCommand
{
    public function handle(CommandContext $context): string
    {
        $key = 'counter:'.$context->command->name;
        $value = CommandCounter::bump($key);

        return ucfirst($context->command->name).': '.$value;
    }
}
