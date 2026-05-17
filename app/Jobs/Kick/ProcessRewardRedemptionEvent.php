<?php

namespace App\Jobs\Kick;

use App\Concerns\SyncsKickUser;
use App\Models\KickWebhookEvent;
use App\Models\RewardRedemption;
use Illuminate\Support\Carbon;

class ProcessRewardRedemptionEvent extends ProcessKickEvent
{
    use SyncsKickUser;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        $redeemedAt = $this->timestamp($payload, $event);

        RewardRedemption::updateOrCreate(
            ['kick_redemption_id' => data_get($payload, 'id')],
            [
                'reward_title' => data_get($payload, 'reward.title'),
                'reward_cost' => data_get($payload, 'reward.cost'),
                'redeemer_kick_user_id' => data_get($payload, 'redeemer.user_id'),
                'redeemer_username' => data_get($payload, 'redeemer.username', 'unknown'),
                'user_input' => data_get($payload, 'user_input'),
                'status' => data_get($payload, 'status'),
                'redeemed_at' => $redeemedAt,
            ],
        );

        $this->syncKickUser(
            data_get($payload, 'redeemer.user_id'),
            data_get($payload, 'redeemer.username', 'unknown'),
            null,
            $redeemedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timestamp(array $payload, KickWebhookEvent $event): Carbon
    {
        return rescue(
            fn () => Carbon::parse(data_get($payload, 'redeemed_at')),
            $event->kick_timestamp ?? now(),
            false,
        );
    }
}
