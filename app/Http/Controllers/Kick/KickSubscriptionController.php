<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\KickConnection;
use App\Models\KickEventSubscription;
use App\Services\Kick\KickApiClient;
use App\Services\Kick\KickEventMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
                $this->upsert($subscription);
            }

            $this->toast('success', __('Subscribed to all Kick events.'));
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('Could not create subscriptions: :error', ['error' => $this->reason($e)]));
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

            $remote->each(fn (array $sub) => $this->upsert($sub));

            $remoteIds = $remote
                ->map(fn (array $sub): ?string => $sub['id'] ?? null)
                ->filter()
                ->all();

            KickEventSubscription::query()
                ->whereNotNull('kick_subscription_id')
                ->when($remoteIds !== [], fn ($query) => $query->whereNotIn('kick_subscription_id', $remoteIds))
                ->update(['status' => KickEventSubscription::STATUS_DELETED]);

            $this->toast('success', __('Subscriptions synced with Kick.'));
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('Sync failed: :error', ['error' => $this->reason($e)]));
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
            $this->toast('error', __('Could not remove the subscription: :error', ['error' => $this->reason($e)]));
        }

        return to_route('kick.subscriptions');
    }

    /**
     * Upsert a remote subscription keyed on Kick's own subscription id (the
     * only reliably unique field). Entries without an id are skipped so the
     * unique constraint can never be violated.
     *
     * @param  array<string, mixed>  $sub
     */
    private function upsert(array $sub): void
    {
        $kickSubscriptionId = $sub['id'] ?? $sub['subscription_id'] ?? null;

        if ($kickSubscriptionId === null) {
            return;
        }

        KickEventSubscription::updateOrCreate(
            ['kick_subscription_id' => $kickSubscriptionId],
            [
                'event_name' => $sub['name'] ?? $sub['event'] ?? data_get($sub, 'event.name', ''),
                'event_version' => $sub['version'] ?? data_get($sub, 'event.version', 1),
                'method' => $sub['method'] ?? 'webhook',
                'broadcaster_user_id' => $sub['broadcaster_user_id'] ?? null,
                'status' => KickEventSubscription::STATUS_ACTIVE,
                'last_synced_at' => now(),
            ],
        );
    }

    /**
     * A short, safe error string for the user-facing toast.
     */
    private function reason(Throwable $e): string
    {
        return Str::limit($e->getMessage(), 180);
    }

    private function toast(string $type, string $message): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
