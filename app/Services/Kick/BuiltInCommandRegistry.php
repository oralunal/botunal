<?php

namespace App\Services\Kick;

use App\Services\Kick\BuiltIns\CommandsListCommand;
use App\Services\Kick\BuiltIns\CounterCommand;
use App\Services\Kick\BuiltIns\FollowageCommand;
use App\Services\Kick\BuiltIns\ShoutoutCommand;
use App\Services\Kick\BuiltIns\UptimeCommand;
use App\Services\Kick\Contracts\BuiltInCommand;

/**
 * Resolves dynamic command handler keys to their implementations.
 */
class BuiltInCommandRegistry
{
    /**
     * @return array<string, class-string<BuiltInCommand>>
     */
    public static function map(): array
    {
        return [
            'uptime' => UptimeCommand::class,
            'commands' => CommandsListCommand::class,
            'counter' => CounterCommand::class,
            'followage' => FollowageCommand::class,
            'shoutout' => ShoutoutCommand::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function handlers(): array
    {
        return array_keys(self::map());
    }

    public function resolve(string $handler): ?BuiltInCommand
    {
        $class = self::map()[$handler] ?? null;

        return $class === null ? null : app($class);
    }
}
