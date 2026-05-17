<?php

namespace App\Services\Kick\BuiltIns;

use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UptimeCommand implements BuiltInCommand
{
    public function handle(CommandContext $context): string
    {
        $startedAt = Cache::get('kick:livestream:started_at');

        if (! Cache::get('kick:livestream:is_live', false) || $startedAt === null) {
            return $context->channelSlug().' is offline.';
        }

        $duration = now()->diffForHumans(Carbon::parse($startedAt), true);

        return 'Stream has been live for '.$duration.'.';
    }
}
