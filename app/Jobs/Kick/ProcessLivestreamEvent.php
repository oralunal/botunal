<?php

namespace App\Jobs\Kick;

use App\Models\KickWebhookEvent;
use App\Models\LivestreamEvent;
use Illuminate\Support\Facades\Cache;

class ProcessLivestreamEvent extends ProcessKickEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        $isStatus = $event->event_type === 'livestream.status.updated';

        LivestreamEvent::create([
            'event' => $isStatus ? LivestreamEvent::EVENT_STATUS : LivestreamEvent::EVENT_METADATA,
            'is_live' => $isStatus ? (bool) data_get($payload, 'is_live') : null,
            'title' => data_get($payload, $isStatus ? 'title' : 'metadata.title'),
            'category' => data_get($payload, 'metadata.category.name'),
            'viewer_count' => data_get($payload, 'viewer_count'),
            'payload' => $payload,
            'occurred_at' => $event->kick_timestamp ?? now(),
        ]);

        if ($isStatus) {
            $this->cacheLiveState($payload);
        }
    }

    /**
     * Cache the live flag and start time for !uptime and timer gating.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cacheLiveState(array $payload): void
    {
        $isLive = (bool) data_get($payload, 'is_live');

        Cache::forever('kick:livestream:is_live', $isLive);

        if ($isLive) {
            Cache::forever('kick:livestream:started_at', data_get($payload, 'started_at'));
        } else {
            Cache::forget('kick:livestream:started_at');
        }
    }
}
