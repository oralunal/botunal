<?php

namespace App\Services\Kick\BuiltIns;

use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;
use App\Services\Kick\WikiLookup;

/**
 * "!wiki <term>" — bilingual Dead by Daylight glossary lookup.
 */
class WikiCommand implements BuiltInCommand
{
    public function __construct(private readonly WikiLookup $lookup) {}

    public function handle(CommandContext $context): string
    {
        return $this->lookup->answer($context->argString());
    }
}
