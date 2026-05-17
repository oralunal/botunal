<?php

namespace App\Services\Kick;

use App\Models\ChatMessage;
use App\Models\KickConnection;
use RuntimeException;

/**
 * Resolves Kick identifiers needed for moderation actions.
 */
class KickResolver
{
    /**
     * The broadcaster (channel) user id, required by moderation endpoints.
     */
    public function broadcasterUserId(): int
    {
        $connection = KickConnection::channel();

        if ($connection?->broadcaster_user_id === null) {
            throw new RuntimeException('Channel connection has no broadcaster id.');
        }

        return (int) $connection->broadcaster_user_id;
    }

    /**
     * Resolve a target user id from a numeric id or a username.
     *
     * Kick only looks up users by id, so for a username we fall back to the
     * most recent chat message we logged from that user.
     */
    public function resolveUserId(string $identifier): ?int
    {
        $identifier = ltrim(trim($identifier), '@');

        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        return ChatMessage::query()
            ->whereRaw('LOWER(sender_username) = ?', [strtolower($identifier)])
            ->whereNotNull('sender_kick_user_id')
            ->latest('sent_at')
            ->value('sender_kick_user_id');
    }
}
