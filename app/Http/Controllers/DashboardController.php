<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\KickFollow;
use App\Models\KickGift;
use App\Models\KickSubscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Personal panel for the logged-in user. For Kick members it surfaces
     * their channel activity (follow date, last messages, subscriptions,
     * gifts they sent). For password admins it stays minimal — they use the
     * Kick panel for streaming work.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $kickId = $user->kick_user_id;

        if ($kickId === null) {
            return Inertia::render('Dashboard', [
                'isKickMember' => false,
            ]);
        }

        $follow = KickFollow::query()
            ->where('follower_kick_user_id', $kickId)
            ->latest('followed_at')
            ->first();

        $recentMessages = ChatMessage::query()
            ->where('sender_kick_user_id', $kickId)
            ->latest('sent_at')
            ->limit(10)
            ->get(['id', 'content', 'sent_at'])
            ->map(fn (ChatMessage $m): array => [
                'content' => $m->content,
                'sent_at' => $m->sent_at?->toIso8601String(),
            ])
            ->all();

        $recentSubscriptions = KickSubscription::query()
            ->where('subscriber_kick_user_id', $kickId)
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'type', 'tier', 'gifter_username', 'duration', 'occurred_at'])
            ->map(fn (KickSubscription $s): array => [
                'type' => $s->type,
                'tier' => $s->tier,
                'gifter_username' => $s->gifter_username,
                'duration' => $s->duration,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
            ])
            ->all();

        $recentGifts = KickGift::query()
            ->where('sender_kick_user_id', $kickId)
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'gift_name', 'kicks_amount', 'occurred_at'])
            ->map(fn (KickGift $g): array => [
                'gift_name' => $g->gift_name,
                'kicks_amount' => $g->kicks_amount,
                'occurred_at' => $g->occurred_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'isKickMember' => true,
            'kick_username' => $user->kick_username,
            'follow' => $follow
                ? ['followed_at' => $follow->followed_at?->toIso8601String()]
                : null,
            'recent_messages' => $recentMessages,
            'recent_subscriptions' => $recentSubscriptions,
            'recent_gifts' => $recentGifts,
        ]);
    }
}
