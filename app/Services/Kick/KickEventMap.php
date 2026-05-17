<?php

namespace App\Services\Kick;

use App\Jobs\Kick\ProcessChatMessageEvent;
use App\Jobs\Kick\ProcessFollowEvent;
use App\Jobs\Kick\ProcessKicksGiftedEvent;
use App\Jobs\Kick\ProcessLivestreamEvent;
use App\Jobs\Kick\ProcessModerationBannedEvent;
use App\Jobs\Kick\ProcessRewardRedemptionEvent;
use App\Jobs\Kick\ProcessSubscriptionEvent;

/**
 * Maps Kick webhook event names to the queued job that projects them.
 */
class KickEventMap
{
    /**
     * All event names (with version) the application subscribes to.
     *
     * @return array<int, array{name: string, version: int}>
     */
    public static function subscribable(): array
    {
        return array_map(
            fn (string $name): array => ['name' => $name, 'version' => 1],
            array_keys(self::map()),
        );
    }

    /**
     * Resolve the job class for an event type, or null if unsupported.
     *
     * @return class-string|null
     */
    public static function jobFor(string $eventType): ?string
    {
        return self::map()[$eventType] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    private static function map(): array
    {
        return [
            'chat.message.sent' => ProcessChatMessageEvent::class,
            'channel.followed' => ProcessFollowEvent::class,
            'channel.subscription.new' => ProcessSubscriptionEvent::class,
            'channel.subscription.renewal' => ProcessSubscriptionEvent::class,
            'channel.subscription.gifts' => ProcessSubscriptionEvent::class,
            'channel.reward.redemption.updated' => ProcessRewardRedemptionEvent::class,
            'livestream.status.updated' => ProcessLivestreamEvent::class,
            'livestream.metadata.updated' => ProcessLivestreamEvent::class,
            'moderation.banned' => ProcessModerationBannedEvent::class,
            'kicks.gifted' => ProcessKicksGiftedEvent::class,
        ];
    }
}
