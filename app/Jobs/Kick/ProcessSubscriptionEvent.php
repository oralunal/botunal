<?php

namespace App\Jobs\Kick;

use App\Models\KickSubscription;
use App\Models\KickWebhookEvent;
use Illuminate\Support\Carbon;

class ProcessSubscriptionEvent extends ProcessKickEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        $type = match ($event->event_type) {
            'channel.subscription.gifts' => KickSubscription::TYPE_GIFT,
            'channel.subscription.renewal' => KickSubscription::TYPE_RENEWAL,
            default => KickSubscription::TYPE_NEW,
        };

        $giftees = data_get($payload, 'giftees', []);

        KickSubscription::create([
            'type' => $type,
            'subscriber_kick_user_id' => data_get($payload, 'subscriber.user_id'),
            'subscriber_username' => data_get($payload, 'subscriber.username'),
            'gifter_username' => data_get($payload, 'gifter.username'),
            'tier' => data_get($payload, 'tier'),
            'duration' => data_get($payload, 'duration'),
            'quantity' => $type === KickSubscription::TYPE_GIFT ? count($giftees) : null,
            'occurred_at' => $this->timestamp($payload, $event),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timestamp(array $payload, KickWebhookEvent $event): Carbon
    {
        return rescue(
            fn () => Carbon::parse(data_get($payload, 'created_at')),
            $event->kick_timestamp ?? now(),
            false,
        );
    }
}
