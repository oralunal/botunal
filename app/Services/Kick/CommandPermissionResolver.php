<?php

namespace App\Services\Kick;

use App\Models\ChatMessage;
use App\Models\Command;

/**
 * Resolves whether a chat message's sender may run a command, based on the
 * badges in the message's sender identity and the command's permission tier.
 */
class CommandPermissionResolver
{
    /** @var array<string, int> */
    private const array RANK = [
        Command::PERMISSION_EVERYONE => 0,
        Command::PERMISSION_SUBSCRIBER => 1,
        Command::PERMISSION_MODERATOR => 2,
        Command::PERMISSION_BROADCASTER => 3,
    ];

    public function allows(ChatMessage $message, Command $command): bool
    {
        $required = self::RANK[$command->permission] ?? 0;

        return $this->senderRank($message) >= $required;
    }

    private function senderRank(ChatMessage $message): int
    {
        // The channel owner (slug matches sender) always ranks highest.
        if (strcasecmp($message->sender_username, (string) config('services.kick.channel_slug')) === 0) {
            return self::RANK[Command::PERMISSION_BROADCASTER];
        }

        $badgeTypes = collect($message->sender_identity['badges'] ?? [])
            ->pluck('type')
            ->map(fn ($type): string => strtolower((string) $type))
            ->all();

        return match (true) {
            in_array('broadcaster', $badgeTypes, true) => self::RANK[Command::PERMISSION_BROADCASTER],
            in_array('moderator', $badgeTypes, true) => self::RANK[Command::PERMISSION_MODERATOR],
            $this->isSubscriber($badgeTypes) => self::RANK[Command::PERMISSION_SUBSCRIBER],
            default => self::RANK[Command::PERMISSION_EVERYONE],
        };
    }

    /**
     * @param  array<int, string>  $badgeTypes
     */
    private function isSubscriber(array $badgeTypes): bool
    {
        return (bool) array_intersect($badgeTypes, ['subscriber', 'founder', 'sub_gifter']);
    }
}
