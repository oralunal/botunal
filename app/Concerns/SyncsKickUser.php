<?php

namespace App\Concerns;

use App\Models\KickUser;
use App\Models\KickUserNameChange;
use Carbon\CarbonInterface;

/**
 * Keeps the kick_users registry (and its rename history) in sync as projection
 * jobs ingest webhook events. Call from a job's project() method so it runs
 * inside the existing event transaction.
 */
trait SyncsKickUser
{
    /**
     * Upsert the registry entry for a Kick user seen in an event, recording a
     * rename when a known kick_user_id reappears under a new username.
     *
     * @param  array<string, mixed>|null  $identity
     */
    protected function syncKickUser(
        int|string|null $kickUserId,
        ?string $username,
        ?array $identity,
        CarbonInterface $seenAt,
    ): void {
        $kickUserId = is_numeric($kickUserId) ? (int) $kickUserId : null;
        $username = trim((string) $username);

        if ($username === '') {
            $username = 'unknown';
        }

        // An event with no id and no real username is not a trackable user
        // (e.g. a gift subscription that carries no single subscriber).
        if ($kickUserId === null && $username === 'unknown') {
            return;
        }

        if ($kickUserId !== null) {
            $this->syncKickUserById($kickUserId, $username, $identity, $seenAt);

            return;
        }

        $this->syncKickUserByUsername($username, $identity, $seenAt);
    }

    /**
     * @param  array<string, mixed>|null  $identity
     */
    private function syncKickUserById(int $kickUserId, string $username, ?array $identity, CarbonInterface $seenAt): void
    {
        $user = KickUser::firstOrCreate(
            ['kick_user_id' => $kickUserId],
            [
                'username' => $username,
                'identity' => $identity,
                'first_seen_at' => $seenAt,
                'last_seen_at' => $seenAt,
            ],
        );

        // Only newer observations move the user forward; out-of-order events
        // must not rewrite the current username or rename history.
        if ($user->wasRecentlyCreated || $user->last_seen_at->gte($seenAt)) {
            return;
        }

        if (strtolower($user->username) !== strtolower($username)) {
            KickUserNameChange::firstOrCreate(
                [
                    'kick_user_id' => $kickUserId,
                    'previous_username' => $user->username,
                    'new_username' => $username,
                ],
                ['changed_at' => $seenAt],
            );
        }

        $user->update([
            'username' => $username,
            'identity' => $identity ?? $user->identity,
            'last_seen_at' => $seenAt,
        ]);
    }

    /**
     * Legacy path: users we only ever see without a Kick user id are keyed by
     * their lowercased username. Renames cannot be tracked reliably here.
     *
     * @param  array<string, mixed>|null  $identity
     */
    private function syncKickUserByUsername(string $username, ?array $identity, CarbonInterface $seenAt): void
    {
        $user = KickUser::firstOrCreate(
            ['kick_user_id' => null, 'username' => strtolower($username)],
            [
                'identity' => $identity,
                'first_seen_at' => $seenAt,
                'last_seen_at' => $seenAt,
            ],
        );

        if (! $user->wasRecentlyCreated && $user->last_seen_at->lt($seenAt)) {
            $user->update(['last_seen_at' => $seenAt]);
        }
    }
}
