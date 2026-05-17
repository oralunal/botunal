<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Models\KickFollow;
use App\Models\KickGift;
use App\Models\KickSubscription;
use App\Models\KickUser;
use App\Models\KickUserNameChange;
use App\Models\RewardRedemption;
use App\Services\Kick\KickApiClient;
use App\Services\Kick\KickResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    /**
     * The newest events shown on a user's timeline, per source.
     */
    private const TIMELINE_LIMIT = 200;

    public function __construct(
        private readonly KickApiClient $api,
        private readonly KickResolver $resolver,
    ) {}

    /**
     * Paginated, searchable registry of every Kick user we've seen.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('username')->trim()->toString() ?: null;

        $users = KickUser::query()
            ->when($search, fn (Builder $q, string $term) => $q->search($term))
            ->latest('last_seen_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('kick/Users', [
            'users' => $users,
            'filters' => ['username' => $search],
        ]);
    }

    /**
     * A single user: identity + moderation status (immediate), messages and a
     * merged event timeline (deferred).
     */
    public function show(Request $request, KickUser $kickUser): Response
    {
        $status = $kickUser->banStatus();

        return Inertia::render('kick/UserShow', [
            'user' => [
                'id' => $kickUser->id,
                'kick_user_id' => $kickUser->kick_user_id,
                'username' => $kickUser->username,
                'identity' => $kickUser->identity,
                'first_seen_at' => $kickUser->first_seen_at?->toIso8601String(),
                'last_seen_at' => $kickUser->last_seen_at?->toIso8601String(),
                'ban_status' => $status->toArray(),
                'former_usernames' => $this->formerUsernames($kickUser),
            ],
            'filters' => ['deleted_only' => $request->boolean('deleted_only')],
            'messages' => Inertia::defer(fn () => $this->buildMessages($request, $kickUser)),
            'events' => Inertia::defer(fn () => $this->buildEvents($kickUser)),
        ]);
    }

    /**
     * Resolve a username (current or former) to its detail page. Lets every
     * page link a displayed username straight to the user without needing the
     * registry id in the payload.
     */
    public function lookup(string $username): RedirectResponse
    {
        $user = $this->resolveByUsername($username);

        if ($user === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No user found for ":name".', ['name' => $username]),
            ]);

            return redirect()->route('kick.users.index', ['username' => $username]);
        }

        return redirect()->route('kick.users.show', $user);
    }

    /**
     * Remove an active ban or timeout for this user.
     */
    public function unban(Request $request, KickUser $kickUser): RedirectResponse
    {
        if ($kickUser->kick_user_id === null) {
            return $this->fail(__('No Kick user id on record for this user.'));
        }

        try {
            $this->api->unbanUser($this->resolver->broadcasterUserId(), $kickUser->kick_user_id);
        } catch (Throwable $e) {
            report($e);

            return $this->fail(__('Kick rejected the unban: :msg', ['msg' => $e->getMessage()]));
        }

        KickBan::create([
            'target_kick_user_id' => $kickUser->kick_user_id,
            'target_username' => $kickUser->username,
            'moderator_username' => $request->user()?->name,
            'action' => KickBan::ACTION_UNBAN,
            'source' => KickBan::SOURCE_DASHBOARD,
            'occurred_at' => now(),
        ]);

        return $this->ok(__('Restriction removed.'));
    }

    /**
     * @return LengthAwarePaginator<int, ChatMessage>
     */
    private function buildMessages(Request $request, KickUser $kickUser): LengthAwarePaginator
    {
        return $this->scopeToUser(ChatMessage::query(), 'sender_kick_user_id', 'sender_username', $kickUser)
            ->when($request->boolean('deleted_only'), fn (Builder $q) => $q->whereNotNull('deleted_at'))
            ->latest('sent_at')
            ->paginate(50, [
                'id', 'kick_message_id', 'sender_username', 'content', 'sent_at', 'deleted_at',
            ])
            ->withQueryString();
    }

    /**
     * A single, time-ordered timeline merged from every per-user event source.
     *
     * @return array{items: list<array<string, mixed>>, truncated: bool}
     */
    private function buildEvents(KickUser $kickUser): array
    {
        $follows = $this->scopeToUser(KickFollow::query(), 'follower_kick_user_id', 'follower_username', $kickUser)
            ->latest('followed_at')->limit(self::TIMELINE_LIMIT)->get()
            ->map(fn (KickFollow $f) => [
                'type' => 'follow',
                'at' => $f->followed_at?->toIso8601String(),
            ]);

        $subscriptions = $this->scopeToUser(KickSubscription::query(), 'subscriber_kick_user_id', 'subscriber_username', $kickUser)
            ->latest('occurred_at')->limit(self::TIMELINE_LIMIT)->get()
            ->map(fn (KickSubscription $s) => [
                'type' => 'subscription',
                'at' => $s->occurred_at?->toIso8601String(),
                'sub_type' => $s->type,
                'tier' => $s->tier,
                'duration' => $s->duration,
                'gifter_username' => $s->gifter_username,
            ]);

        $gifts = $this->scopeToUser(KickGift::query(), 'sender_kick_user_id', 'sender_username', $kickUser)
            ->latest('occurred_at')->limit(self::TIMELINE_LIMIT)->get()
            ->map(fn (KickGift $g) => [
                'type' => 'gift_sent',
                'at' => $g->occurred_at?->toIso8601String(),
                'gift_name' => $g->gift_name,
                'kicks_amount' => $g->kicks_amount,
                'recipient_username' => $g->recipient_username,
                'message' => $g->message,
            ]);

        $redemptions = $this->scopeToUser(RewardRedemption::query(), 'redeemer_kick_user_id', 'redeemer_username', $kickUser)
            ->latest('redeemed_at')->limit(self::TIMELINE_LIMIT)->get()
            ->map(fn (RewardRedemption $r) => [
                'type' => 'redemption',
                'at' => $r->redeemed_at?->toIso8601String(),
                'reward_title' => $r->reward_title,
                'reward_cost' => $r->reward_cost,
                'status' => $r->status,
                'user_input' => $r->user_input,
            ]);

        $bans = $this->scopeToUser(KickBan::query(), 'target_kick_user_id', 'target_username', $kickUser)
            ->latest('occurred_at')->limit(self::TIMELINE_LIMIT)->get()
            ->map(fn (KickBan $b) => [
                'type' => 'ban',
                'at' => $b->occurred_at?->toIso8601String(),
                'action' => $b->action,
                'reason' => $b->reason,
                'moderator_username' => $b->moderator_username,
                'source' => $b->source,
                'expires_at' => $b->expires_at?->toIso8601String(),
            ]);

        $renames = collect();

        if ($kickUser->kick_user_id !== null) {
            $renames = KickUserNameChange::query()
                ->where('kick_user_id', $kickUser->kick_user_id)
                ->latest('changed_at')->limit(self::TIMELINE_LIMIT)->get()
                ->map(fn (KickUserNameChange $c) => [
                    'type' => 'rename',
                    'at' => $c->changed_at?->toIso8601String(),
                    'previous_username' => $c->previous_username,
                    'new_username' => $c->new_username,
                ]);
        }

        $merged = $follows
            ->concat($subscriptions)
            ->concat($gifts)
            ->concat($redemptions)
            ->concat($bans)
            ->concat($renames)
            ->sortByDesc('at')
            ->values();

        return [
            'items' => $merged->take(self::TIMELINE_LIMIT)->all(),
            'truncated' => $merged->count() > self::TIMELINE_LIMIT,
        ];
    }

    /**
     * @return list<string>
     */
    private function formerUsernames(KickUser $kickUser): array
    {
        if ($kickUser->kick_user_id === null) {
            return [];
        }

        $changes = KickUserNameChange::query()
            ->where('kick_user_id', $kickUser->kick_user_id)
            ->get(['previous_username', 'new_username']);

        return $changes
            ->flatMap(fn (KickUserNameChange $c) => [$c->previous_username, $c->new_username])
            ->reject(fn (string $name) => strtolower($name) === strtolower($kickUser->username))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Scope a query to the given user by Kick id, falling back to a
     * case-insensitive username match for legacy rows with no id.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function scopeToUser(Builder $query, string $idColumn, string $nameColumn, KickUser $kickUser): Builder
    {
        if ($kickUser->kick_user_id !== null) {
            return $query->where($idColumn, $kickUser->kick_user_id);
        }

        return $query->whereRaw("LOWER({$nameColumn}) = ?", [strtolower($kickUser->username)]);
    }

    /**
     * Find the registry row for a username: by current name, then by rename
     * history, then by the most recent chat message that carried an id.
     */
    private function resolveByUsername(string $username): ?KickUser
    {
        $lower = strtolower(trim($username));

        if ($lower === '' || $lower === 'unknown') {
            return null;
        }

        $user = KickUser::query()
            ->whereRaw('LOWER(username) = ?', [$lower])
            ->first();

        if ($user !== null) {
            return $user;
        }

        $kickUserId = KickUserNameChange::query()
            ->where(function (Builder $q) use ($lower) {
                $q->whereRaw('LOWER(previous_username) = ?', [$lower])
                    ->orWhereRaw('LOWER(new_username) = ?', [$lower]);
            })
            ->latest('changed_at')
            ->value('kick_user_id');

        $kickUserId ??= ChatMessage::query()
            ->whereRaw('LOWER(sender_username) = ?', [$lower])
            ->whereNotNull('sender_kick_user_id')
            ->latest('sent_at')
            ->value('sender_kick_user_id');

        if ($kickUserId === null) {
            return null;
        }

        return KickUser::query()->where('kick_user_id', $kickUserId)->first();
    }

    private function ok(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
