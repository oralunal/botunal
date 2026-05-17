<?php

namespace App\Services\Kick;

use App\Models\KickConnection;

/**
 * The OAuth scopes requested for each connection type.
 */
class KickScopes
{
    public const CHANNEL = [
        'events:subscribe',
        'moderation:ban',
        'moderation:chat_message:manage',
        'channel:read',
        'user:read',
        'channel:rewards:read',
        'kicks:read',
    ];

    public const BOT = [
        'chat:write',
        'user:read',
    ];

    /**
     * Scopes required for the given connection type.
     *
     * @return array<int, string>
     */
    public static function for(string $type): array
    {
        return $type === KickConnection::TYPE_BOT ? self::BOT : self::CHANNEL;
    }
}
