<?php

namespace App\Services\Kick;

use App\Models\ChatMessage;
use App\Models\Command;

/**
 * Immutable invocation context handed to the renderer and built-in commands.
 */
class CommandContext
{
    /**
     * @param  array<int, string>  $args
     */
    public function __construct(
        public readonly ChatMessage $message,
        public readonly Command $command,
        public readonly string $trigger,
        public readonly array $args,
    ) {}

    public function username(): string
    {
        return $this->message->sender_username;
    }

    public function channelSlug(): string
    {
        return (string) config('services.kick.channel_slug');
    }

    /**
     * The argument string after the trigger (e.g. "!so foo bar" => "foo bar").
     */
    public function argString(): string
    {
        return implode(' ', $this->args);
    }
}
