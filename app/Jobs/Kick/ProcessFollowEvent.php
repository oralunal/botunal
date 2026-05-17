<?php

namespace App\Jobs\Kick;

use App\Concerns\SyncsKickUser;
use App\Models\KickFollow;
use App\Models\KickWebhookEvent;

class ProcessFollowEvent extends ProcessKickEvent
{
    use SyncsKickUser;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        $followedAt = $event->kick_timestamp ?? now();

        KickFollow::create([
            'follower_kick_user_id' => data_get($payload, 'follower.user_id'),
            'follower_username' => data_get($payload, 'follower.username', 'unknown'),
            'followed_at' => $followedAt,
        ]);

        $this->syncKickUser(
            data_get($payload, 'follower.user_id'),
            data_get($payload, 'follower.username', 'unknown'),
            null,
            $followedAt,
        );
    }
}
