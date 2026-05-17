<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Models\KickConnection;
use App\Models\KickFollow;
use App\Models\KickGift;
use App\Models\KickSubscription;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class KickDashboardController extends Controller
{
    /**
     * Overview counters plus a deferred recent-activity feed.
     */
    public function index(): Response
    {
        return Inertia::render('kick/Dashboard', [
            'connections' => [
                'channel' => KickConnection::channel() !== null,
                'bot' => KickConnection::bot() !== null,
            ],
            'is_live' => (bool) Cache::get('kick:livestream:is_live', false),
            'counts' => fn (): array => [
                'messages' => ChatMessage::whereDate('sent_at', today())->count(),
                'follows' => KickFollow::whereDate('followed_at', today())->count(),
                'subscriptions' => KickSubscription::whereDate('occurred_at', today())->count(),
                'kicks' => (int) KickGift::whereDate('occurred_at', today())->sum('kicks_amount'),
                'bans' => KickBan::whereDate('occurred_at', today())->count(),
            ],
            'feed' => Inertia::defer(fn (): array => $this->recentFeed()),
        ]);
    }

    /**
     * A merged, time-ordered slice of the most recent events.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentFeed(): array
    {
        $messages = ChatMessage::query()->latest('sent_at')->limit(15)->get()
            ->map(fn (ChatMessage $m): array => [
                'type' => 'message',
                'actor' => $m->sender_username,
                'detail' => $m->content,
                'at' => $m->sent_at?->toIso8601String(),
            ]);

        $follows = KickFollow::query()->latest('followed_at')->limit(15)->get()
            ->map(fn (KickFollow $f): array => [
                'type' => 'follow',
                'actor' => $f->follower_username,
                'detail' => null,
                'at' => $f->followed_at?->toIso8601String(),
            ]);

        return $messages->concat($follows)
            ->sortByDesc('at')
            ->take(20)
            ->values()
            ->all();
    }
}
