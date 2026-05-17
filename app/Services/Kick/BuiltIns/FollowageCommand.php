<?php

namespace App\Services\Kick\BuiltIns;

use App\Models\KickFollow;
use App\Services\Kick\CommandContext;
use App\Services\Kick\Contracts\BuiltInCommand;

/**
 * Reports how long ago a user followed.
 *
 * Limitation: Kick exposes no public "followage" endpoint, so this only
 * knows about follows captured by this app since it went live.
 */
class FollowageCommand implements BuiltInCommand
{
    public function handle(CommandContext $context): string
    {
        $username = $context->args[0] ?? $context->username();

        $follow = KickFollow::query()
            ->where('follower_username', $username)
            ->latest('followed_at')
            ->first();

        if ($follow === null) {
            return "No recorded follow for {$username}.";
        }

        return "{$username} has been following for ".$follow->followed_at->diffForHumans(null, true).'.';
    }
}
