<?php

namespace App\Jobs\Kick;

use App\Models\KickFollow;
use App\Models\KickWebhookEvent;

class ProcessFollowEvent extends ProcessKickEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        KickFollow::create([
            'follower_kick_user_id' => data_get($payload, 'follower.user_id'),
            'follower_username' => data_get($payload, 'follower.username', 'unknown'),
            'followed_at' => $event->kick_timestamp ?? now(),
        ]);
    }
}
