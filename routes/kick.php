<?php

use App\Http\Controllers\Kick\ChatMessageController;
use App\Http\Controllers\Kick\CommandController;
use App\Http\Controllers\Kick\CommandLogController;
use App\Http\Controllers\Kick\KickConnectionController;
use App\Http\Controllers\Kick\KickDashboardController;
use App\Http\Controllers\Kick\KickEventController;
use App\Http\Controllers\Kick\KickOAuthCallbackController;
use App\Http\Controllers\Kick\KickOAuthController;
use App\Http\Controllers\Kick\KickSubscriptionController;
use App\Http\Controllers\Kick\MemberController;
use App\Http\Controllers\Kick\MemberMessageController;
use App\Http\Controllers\Kick\ModerationController;
use App\Http\Controllers\Kick\TimerController;
use App\Http\Controllers\Kick\UserController;
use App\Http\Controllers\Kick\WebhookController;
use App\Http\Controllers\Kick\WikiController;
use Illuminate\Support\Facades\Route;

/*
 * Public, unauthenticated webhook ingress. CSRF is excluded in bootstrap/app.php
 * and the request is signature-verified by the kick.signature middleware.
 */
Route::post('/kick/webhook', WebhookController::class)
    ->middleware('kick.signature')
    ->name('kick.webhook');

/*
 * Public OAuth callback. Kick only allows a single redirect_uri, so this
 * dispatcher peeks at the cached state and forwards to the admin connection
 * flow or the member login flow. It must stay outside the auth group because
 * the member login flow is for unauthenticated visitors.
 */
Route::get('/kick/oauth/callback', KickOAuthCallbackController::class)
    ->name('kick.oauth.callback');

Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
    // OAuth (PKCE) connect/disconnect.
    Route::middleware('permission:connections.manage')->group(function () {
        Route::get('/kick/oauth/{type}/redirect', [KickOAuthController::class, 'redirect'])
            ->whereIn('type', ['channel', 'bot'])
            ->name('kick.oauth.redirect');
        Route::delete('/kick/connections/{type}', [KickOAuthController::class, 'disconnect'])
            ->whereIn('type', ['channel', 'bot'])
            ->name('kick.connections.disconnect');

        Route::get('/kick/connections', [KickConnectionController::class, 'index'])
            ->name('kick.connections');
    });

    // Webhook event subscription management.
    Route::middleware('permission:subscriptions.manage')->group(function () {
        Route::get('/kick/subscriptions', [KickSubscriptionController::class, 'index'])
            ->name('kick.subscriptions');
        Route::post('/kick/subscriptions', [KickSubscriptionController::class, 'store'])
            ->name('kick.subscriptions.store');
        Route::post('/kick/subscriptions/sync', [KickSubscriptionController::class, 'sync'])
            ->name('kick.subscriptions.sync');
        Route::delete('/kick/subscriptions/{subscription}', [KickSubscriptionController::class, 'destroy'])
            ->name('kick.subscriptions.destroy');
    });

    // Dashboards and logs.
    Route::get('/kick/dashboard', [KickDashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('kick.dashboard');
    Route::get('/kick/messages', [ChatMessageController::class, 'index'])
        ->middleware('permission:messages.view')
        ->name('kick.messages');
    Route::get('/kick/events', [KickEventController::class, 'index'])
        ->middleware('permission:events.view')
        ->name('kick.events');

    // Command engine.
    Route::middleware('permission:commands.manage')->group(function () {
        Route::get('/kick/commands', [CommandController::class, 'index'])
            ->name('kick.commands.index');
        Route::post('/kick/commands', [CommandController::class, 'store'])
            ->name('kick.commands.store');
        Route::put('/kick/commands/{command}', [CommandController::class, 'update'])
            ->name('kick.commands.update');
        Route::delete('/kick/commands/{command}', [CommandController::class, 'destroy'])
            ->name('kick.commands.destroy');
    });

    // Timers.
    Route::middleware('permission:timers.manage')->group(function () {
        Route::get('/kick/timers', [TimerController::class, 'index'])
            ->name('kick.timers.index');
        Route::post('/kick/timers', [TimerController::class, 'store'])
            ->name('kick.timers.store');
        Route::put('/kick/timers/{timer}', [TimerController::class, 'update'])
            ->name('kick.timers.update');
        Route::delete('/kick/timers/{timer}', [TimerController::class, 'destroy'])
            ->name('kick.timers.destroy');
    });

    // Command activity log.
    Route::get('/kick/command-logs', [CommandLogController::class, 'index'])
        ->middleware('permission:command-logs.view')
        ->name('kick.command-logs.index');

    // DBD wiki / glossary.
    Route::get('/kick/wiki', [WikiController::class, 'index'])
        ->middleware('permission:wiki.view')
        ->name('kick.wiki.index');
    Route::post('/kick/wiki', [WikiController::class, 'store'])
        ->middleware('permission:wiki.create')
        ->name('kick.wiki.store');
    Route::put('/kick/wiki/{wikiEntry}', [WikiController::class, 'update'])
        ->middleware('permission:wiki.edit')
        ->name('kick.wiki.update');
    Route::delete('/kick/wiki/{wikiEntry}', [WikiController::class, 'destroy'])
        ->middleware('permission:wiki.delete')
        ->name('kick.wiki.destroy');

    // Member message inbox (panel side).
    Route::middleware('permission:member-messages.view')->group(function () {
        Route::get('/kick/member-messages', [MemberMessageController::class, 'index'])
            ->name('kick.member-messages.index');
        Route::patch('/kick/member-messages/{memberMessage}/read', [MemberMessageController::class, 'markRead'])
            ->name('kick.member-messages.read');
        Route::patch('/kick/member-messages/{memberMessage}/unread', [MemberMessageController::class, 'markUnread'])
            ->name('kick.member-messages.unread');
    });

    // App member & permission management.
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/kick/members', [MemberController::class, 'index'])
            ->name('kick.members.index');
        Route::patch('/kick/members/{user}', [MemberController::class, 'update'])
            ->name('kick.members.update');
    });

    // User registry.
    Route::middleware('permission:kick-users.view')->group(function () {
        Route::get('/kick/users', [UserController::class, 'index'])
            ->name('kick.users.index');
        Route::get('/kick/users/lookup/{username}', [UserController::class, 'lookup'])
            ->name('kick.users.lookup');
        Route::get('/kick/users/{kickUser}', [UserController::class, 'show'])
            ->name('kick.users.show');
    });
    Route::delete('/kick/users/{kickUser}/ban', [UserController::class, 'unban'])
        ->middleware('permission:kick-users.moderate')
        ->name('kick.users.unban');

    // Moderation actions.
    Route::get('/kick/moderation', [ModerationController::class, 'index'])
        ->middleware('permission:moderation.view')
        ->name('kick.moderation.index');
    Route::middleware('permission:moderation.act')->group(function () {
        Route::post('/kick/moderation/ban', [ModerationController::class, 'ban'])
            ->name('kick.moderation.ban');
        Route::delete('/kick/moderation/ban', [ModerationController::class, 'unban'])
            ->name('kick.moderation.unban');
        Route::delete('/kick/moderation/message', [ModerationController::class, 'deleteMessage'])
            ->middleware('throttle:30,1')
            ->name('kick.moderation.message');
    });
});
