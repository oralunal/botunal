<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Landing entry for the Kick panel. The main sidebar "Kick" link sends every
 * user here; we redirect to the first Kick page the user can actually access
 * based on their permissions so members with only e.g. wiki.view don't get a
 * 403 on /kick/dashboard.
 */
class KickLandingController extends Controller
{
    /**
     * Priority-ordered ability → route map. Dashboard wins when granted
     * (matches the admin / super-admin default); otherwise the first ability
     * the user holds wins.
     *
     * @var array<string, string>
     */
    private const ABILITY_ROUTES = [
        Permissions::DASHBOARD_VIEW => 'kick.dashboard',
        Permissions::WIKI_VIEW => 'kick.wiki.index',
        Permissions::COMMANDS_MANAGE => 'kick.commands.index',
        Permissions::TIMERS_MANAGE => 'kick.timers.index',
        Permissions::MESSAGES_VIEW => 'kick.messages',
        Permissions::EVENTS_VIEW => 'kick.events',
        Permissions::SUBSCRIPTIONS_MANAGE => 'kick.subscriptions',
        Permissions::CONNECTIONS_MANAGE => 'kick.connections',
        Permissions::COMMAND_LOGS_VIEW => 'kick.command-logs.index',
        Permissions::MODERATION_VIEW => 'kick.moderation.index',
        Permissions::KICK_USERS_VIEW => 'kick.users.index',
        Permissions::MEMBER_MESSAGES_VIEW => 'kick.member-messages.index',
        Permissions::USERS_MANAGE => 'kick.members.index',
    ];

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        foreach (self::ABILITY_ROUTES as $ability => $route) {
            if ($user?->can($ability)) {
                return redirect()->route($route);
            }
        }

        abort(403);
    }
}
