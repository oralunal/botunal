<?php

namespace App\Jobs\Kick;

use App\Models\KickGift;
use App\Models\KickWebhookEvent;
use Illuminate\Support\Carbon;

class ProcessKicksGiftedEvent extends ProcessKickEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        KickGift::create([
            'sender_kick_user_id' => data_get($payload, 'sender.user_id'),
            'sender_username' => data_get($payload, 'sender.username', 'unknown'),
            'recipient_username' => data_get($payload, 'broadcaster.username'),
            'gift_name' => data_get($payload, 'gift.name'),
            'kicks_amount' => (int) data_get($payload, 'gift.amount', 0),
            'message' => data_get($payload, 'gift.message'),
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
