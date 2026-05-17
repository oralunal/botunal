<?php

namespace App\Console\Commands\Kick;

use App\Models\KickConnection;
use App\Models\KickEventSubscription;
use App\Services\Kick\KickApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('kick:sync-subscriptions')]
#[Description('Reconcile local webhook subscription records with Kick')]
class SyncSubscriptionsCommand extends Command
{
    public function handle(KickApiClient $api): int
    {
        if (KickConnection::channel() === null) {
            $this->warn('No channel connection; skipping subscription sync.');

            return self::SUCCESS;
        }

        try {
            $remote = collect($api->listSubscriptions());
        } catch (Throwable $e) {
            report($e);
            $this->error("Could not list Kick subscriptions: {$e->getMessage()}");

            return self::FAILURE;
        }

        foreach ($remote as $sub) {
            KickEventSubscription::updateOrCreate(
                [
                    'event_name' => $sub['name'] ?? $sub['event'] ?? '',
                    'event_version' => $sub['version'] ?? 1,
                    'broadcaster_user_id' => $sub['broadcaster_user_id'] ?? null,
                ],
                [
                    'kick_subscription_id' => $sub['id'] ?? null,
                    'status' => KickEventSubscription::STATUS_ACTIVE,
                    'last_synced_at' => now(),
                ],
            );
        }

        $remoteIds = $remote->pluck('id')->filter()->all();

        $stale = KickEventSubscription::query()
            ->whereNotNull('kick_subscription_id')
            ->whereNotIn('kick_subscription_id', $remoteIds)
            ->update(['status' => KickEventSubscription::STATUS_DELETED]);

        $this->info("Synced {$remote->count()} subscriptions ({$stale} marked deleted).");

        return self::SUCCESS;
    }
}
