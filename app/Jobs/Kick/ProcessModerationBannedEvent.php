<?php

namespace App\Jobs\Kick;

use App\Concerns\SyncsKickUser;
use App\Models\KickBan;
use App\Models\KickWebhookEvent;
use Illuminate\Support\Carbon;

class ProcessModerationBannedEvent extends ProcessKickEvent
{
    use SyncsKickUser;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        $expiresAt = data_get($payload, 'metadata.expires_at');
        $occurredAt = $this->timestamp($payload, $event);

        KickBan::create([
            'target_kick_user_id' => data_get($payload, 'banned_user.user_id'),
            'target_username' => data_get($payload, 'banned_user.username', 'unknown'),
            'moderator_username' => data_get($payload, 'moderator.username'),
            'action' => $expiresAt !== null ? KickBan::ACTION_TIMEOUT : KickBan::ACTION_BAN,
            'reason' => data_get($payload, 'metadata.reason'),
            'expires_at' => $expiresAt !== null ? Carbon::parse($expiresAt) : null,
            'source' => KickBan::SOURCE_WEBHOOK,
            'occurred_at' => $occurredAt,
        ]);

        $this->syncKickUser(
            data_get($payload, 'banned_user.user_id'),
            data_get($payload, 'banned_user.username', 'unknown'),
            null,
            $occurredAt,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timestamp(array $payload, KickWebhookEvent $event): Carbon
    {
        return rescue(
            fn () => Carbon::parse(data_get($payload, 'metadata.created_at')),
            $event->kick_timestamp ?? now(),
            false,
        );
    }
}
