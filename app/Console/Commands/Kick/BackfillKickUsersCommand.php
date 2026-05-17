<?php

namespace App\Console\Commands\Kick;

use App\Models\KickUser;
use App\Models\KickUserNameChange;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('kick:backfill-users')]
#[Description('Populate kick_users (and rename history) from existing event tables')]
class BackfillKickUsersCommand extends Command
{
    /**
     * Event tables and the columns identifying the user, ordered consistently
     * so the UNION below lines up.
     *
     * @var list<array{table: string, id: string, name: string, identity: string, ts: string}>
     */
    private array $sources = [
        ['table' => 'chat_messages', 'id' => 'sender_kick_user_id', 'name' => 'sender_username', 'identity' => 'sender_identity', 'ts' => 'sent_at'],
        ['table' => 'kick_follows', 'id' => 'follower_kick_user_id', 'name' => 'follower_username', 'identity' => 'NULL', 'ts' => 'followed_at'],
        ['table' => 'kick_subscriptions', 'id' => 'subscriber_kick_user_id', 'name' => 'subscriber_username', 'identity' => 'NULL', 'ts' => 'occurred_at'],
        ['table' => 'kick_gifts', 'id' => 'sender_kick_user_id', 'name' => 'sender_username', 'identity' => 'NULL', 'ts' => 'occurred_at'],
        ['table' => 'kick_bans', 'id' => 'target_kick_user_id', 'name' => 'target_username', 'identity' => 'NULL', 'ts' => 'occurred_at'],
        ['table' => 'reward_redemptions', 'id' => 'redeemer_kick_user_id', 'name' => 'redeemer_username', 'identity' => 'NULL', 'ts' => 'redeemed_at'],
    ];

    public function handle(): int
    {
        $this->info('Backfilling users with a Kick id (reconstructing renames)...');
        $this->backfillIdentifiedUsers();

        $this->info('Backfilling legacy users without a Kick id...');
        $this->backfillAnonymousUsers();

        $this->info(sprintf(
            'Done. %d users, %d recorded name changes.',
            KickUser::count(),
            KickUserNameChange::count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Stream every observation that carries a kick_user_id, ordered by user
     * then time, and rebuild each user's identity and rename chain.
     */
    private function backfillIdentifiedUsers(): void
    {
        $stream = DB::query()
            ->fromSub($this->observationUnion(), 'obs')
            ->orderBy('kick_user_id')
            ->orderBy('ts');

        $currentId = null;
        $currentName = null;
        $firstTs = null;
        $lastTs = null;
        $identity = null;

        foreach ($stream->cursor() as $row) {
            $rowId = (int) $row->kick_user_id;

            if ($rowId !== $currentId) {
                $this->flushIdentifiedUser($currentId, $currentName, $firstTs, $lastTs, $identity);

                $currentId = $rowId;
                $currentName = (string) $row->username;
                $firstTs = $row->ts;
                $lastTs = $row->ts;
                $identity = $this->decodeIdentity($row->identity);

                continue;
            }

            $lastTs = $row->ts;

            $name = (string) $row->username;

            if ($name !== '' && strtolower($name) !== strtolower((string) $currentName)) {
                KickUserNameChange::firstOrCreate(
                    [
                        'kick_user_id' => $currentId,
                        'previous_username' => $currentName,
                        'new_username' => $name,
                    ],
                    ['changed_at' => $row->ts],
                );

                $currentName = $name;
            }

            $decoded = $this->decodeIdentity($row->identity);

            if ($decoded !== null) {
                $identity = $decoded;
            }
        }

        $this->flushIdentifiedUser($currentId, $currentName, $firstTs, $lastTs, $identity);
    }

    /**
     * Upsert the registry row for a fully-walked identified user. Idempotent:
     * a re-run recomputes the same final state.
     *
     * @param  array<string, mixed>|null  $identity
     */
    private function flushIdentifiedUser(
        ?int $kickUserId,
        ?string $username,
        ?string $firstTs,
        ?string $lastTs,
        ?array $identity,
    ): void {
        if ($kickUserId === null) {
            return;
        }

        $first = Carbon::parse($firstTs);
        $last = Carbon::parse($lastTs);

        $user = KickUser::firstOrCreate(
            ['kick_user_id' => $kickUserId],
            [
                'username' => $username,
                'identity' => $identity,
                'first_seen_at' => $first,
                'last_seen_at' => $last,
            ],
        );

        if ($user->wasRecentlyCreated) {
            return;
        }

        $user->update([
            'username' => $username,
            'identity' => $identity ?? $user->identity,
            'first_seen_at' => $user->first_seen_at->min($first),
            'last_seen_at' => $user->last_seen_at->max($last),
        ]);
    }

    /**
     * Legacy users only ever seen without a Kick id, keyed by lowercased
     * username. Renames are not tracked here (parity with SyncsKickUser).
     */
    private function backfillAnonymousUsers(): void
    {
        foreach ($this->sources as $source) {
            DB::table($source['table'])
                ->whereNull($source['id'])
                ->whereRaw("LOWER({$source['name']}) NOT IN ('', 'unknown')")
                ->groupByRaw("LOWER({$source['name']})")
                ->selectRaw(
                    "LOWER({$source['name']}) as uname, MIN({$source['ts']}) as first_ts, MAX({$source['ts']}) as last_ts"
                )
                ->cursor()
                ->each(function (object $row): void {
                    $first = Carbon::parse($row->first_ts);
                    $last = Carbon::parse($row->last_ts);

                    $user = KickUser::firstOrCreate(
                        ['kick_user_id' => null, 'username' => $row->uname],
                        ['first_seen_at' => $first, 'last_seen_at' => $last],
                    );

                    if (! $user->wasRecentlyCreated) {
                        $user->update([
                            'first_seen_at' => $user->first_seen_at->min($first),
                            'last_seen_at' => $user->last_seen_at->max($last),
                        ]);
                    }
                });
        }
    }

    /**
     * A UNION ALL of every event table's user observations.
     */
    private function observationUnion(): Builder
    {
        $query = null;

        foreach ($this->sources as $source) {
            $part = DB::table($source['table'])
                ->whereNotNull($source['id'])
                ->selectRaw(
                    "{$source['id']} as kick_user_id, {$source['name']} as username, {$source['identity']} as identity, {$source['ts']} as ts"
                );

            $query = $query === null ? $part : $query->unionAll($part);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeIdentity(mixed $identity): ?array
    {
        if (! is_string($identity) || $identity === '') {
            return null;
        }

        $decoded = json_decode($identity, true);

        return is_array($decoded) ? $decoded : null;
    }
}
