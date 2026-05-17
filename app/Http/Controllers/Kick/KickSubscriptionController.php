<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\KickConnection;
use App\Models\KickEventSubscription;
use App\Services\Kick\KickApiClient;
use App\Services\Kick\KickEventMap;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KickSubscriptionController extends Controller
{
    public function __construct(private readonly KickApiClient $api) {}

    /**
     * List local subscription records alongside the events we expect.
     */
    public function index(): Response
    {
        return Inertia::render('kick/Subscriptions', [
            'subscriptions' => KickEventSubscription::query()
                ->orderBy('event_name')
                ->get(['id', 'kick_subscription_id', 'event_name', 'event_version', 'status', 'last_synced_at']),
            'expected_events' => array_column(KickEventMap::subscribable(), 'name'),
            'channel_connected' => KickConnection::channel() !== null,
        ]);
    }

    /**
     * Subscribe to every event the application supports.
     */
    public function store(): RedirectResponse
    {
        try {
            $result = $this->api->createSubscription(KickEventMap::subscribable());

            foreach ($result['data'] ?? [] as $subscription) {
                KickEventSubscription::updateOrCreate(
                    [
                        'event_name' => $subscription['name'] ?? $subscription['event'] ?? '',
                        'event_version' => $subscription['version'] ?? 1,
                        'broadcaster_user_id' => $subscription['broadcaster_user_id'] ?? null,
                    ],
                    [
                        'kick_subscription_id' => $subscription['id'] ?? $subscription['subscription_id'] ?? null,
                        'status' => KickEventSubscription::STATUS_ACTIVE,
                        'last_synced_at' => now(),
                    ],
                );
            }

            $this->toast('success', __('Subscribed to all Kick events.'));
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('Could not create subscriptions. Check the channel connection.'));
        }

        return to_route('kick.subscriptions');
    }

    /**
     * Reconcile local subscription state with Kick's remote state.
     */
    public function sync(): RedirectResponse
    {
        try {
            $remote = collect($this->api->listSubscriptions());

            $remote->each(function (array $sub): void {
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
            });

            $remoteIds = $remote->pluck('id')->filter()->all();

            KickEventSubscription::query()
                ->whereNotNull('kick_subscription_id')
                ->whereNotIn('kick_subscription_id', $remoteIds)
                ->update(['status' => KickEventSubscription::STATUS_DELETED]);

            $this->toast('success', __('Subscriptions synced with Kick.'));
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('Sync failed. Check the channel connection.'));
        }

        return to_route('kick.subscriptions');
    }

    /**
     * Delete a single subscription locally and on Kick.
     */
    public function destroy(KickEventSubscription $subscription): RedirectResponse
    {
        try {
            if ($subscription->kick_subscription_id !== null) {
                $this->api->deleteSubscription($subscription->kick_subscription_id);
            }

            $subscription->delete();
            $this->toast('success', __('Subscription removed.'));
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('Could not remove the subscription.'));
        }

        return to_route('kick.subscriptions');
    }

    private function toast(string $type, string $message): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
