<?php

use App\Http\Controllers\Kick\ChatMessageController;
use App\Http\Controllers\Kick\CommandController;
use App\Http\Controllers\Kick\CommandLogController;
use App\Http\Controllers\Kick\KickConnectionController;
use App\Http\Controllers\Kick\KickDashboardController;
use App\Http\Controllers\Kick\KickEventController;
use App\Http\Controllers\Kick\KickOAuthController;
use App\Http\Controllers\Kick\KickSubscriptionController;
use App\Http\Controllers\Kick\ModerationController;
use App\Http\Controllers\Kick\TimerController;
use App\Http\Controllers\Kick\UserController;
use App\Http\Controllers\Kick\WebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Public, unauthenticated webhook ingress. CSRF is excluded in bootstrap/app.php
 * and the request is signature-verified by the kick.signature middleware.
 */
Route::post('/kick/webhook', WebhookController::class)
    ->middleware('kick.signature')
    ->name('kick.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    // OAuth (PKCE) connect/disconnect.
    Route::get('/kick/oauth/{type}/redirect', [KickOAuthController::class, 'redirect'])
        ->whereIn('type', ['channel', 'bot'])
        ->name('kick.oauth.redirect');
    Route::get('/kick/oauth/callback', [KickOAuthController::class, 'callback'])
        ->name('kick.oauth.callback');
    Route::delete('/kick/connections/{type}', [KickOAuthController::class, 'disconnect'])
        ->whereIn('type', ['channel', 'bot'])
        ->name('kick.connections.disconnect');

    Route::get('/kick/connections', [KickConnectionController::class, 'index'])
        ->name('kick.connections');

    // Webhook event subscription management.
    Route::get('/kick/subscriptions', [KickSubscriptionController::class, 'index'])
        ->name('kick.subscriptions');
    Route::post('/kick/subscriptions', [KickSubscriptionController::class, 'store'])
        ->name('kick.subscriptions.store');
    Route::post('/kick/subscriptions/sync', [KickSubscriptionController::class, 'sync'])
        ->name('kick.subscriptions.sync');
    Route::delete('/kick/subscriptions/{subscription}', [KickSubscriptionController::class, 'destroy'])
        ->name('kick.subscriptions.destroy');

    // Dashboards and logs.
    Route::get('/kick/dashboard', [KickDashboardController::class, 'index'])
        ->name('kick.dashboard');
    Route::get('/kick/messages', [ChatMessageController::class, 'index'])
        ->name('kick.messages');
    Route::get('/kick/events', [KickEventController::class, 'index'])
        ->name('kick.events');

    // Command engine.
    Route::get('/kick/commands', [CommandController::class, 'index'])
        ->name('kick.commands.index');
    Route::post('/kick/commands', [CommandController::class, 'store'])
        ->name('kick.commands.store');
    Route::put('/kick/commands/{command}', [CommandController::class, 'update'])
        ->name('kick.commands.update');
    Route::delete('/kick/commands/{command}', [CommandController::class, 'destroy'])
        ->name('kick.commands.destroy');

    // Timers.
    Route::get('/kick/timers', [TimerController::class, 'index'])
        ->name('kick.timers.index');
    Route::post('/kick/timers', [TimerController::class, 'store'])
        ->name('kick.timers.store');
    Route::put('/kick/timers/{timer}', [TimerController::class, 'update'])
        ->name('kick.timers.update');
    Route::delete('/kick/timers/{timer}', [TimerController::class, 'destroy'])
        ->name('kick.timers.destroy');

    // Command activity log.
    Route::get('/kick/command-logs', [CommandLogController::class, 'index'])
        ->name('kick.command-logs.index');

    // User registry.
    Route::get('/kick/users', [UserController::class, 'index'])
        ->name('kick.users.index');
    Route::get('/kick/users/{kickUser}', [UserController::class, 'show'])
        ->name('kick.users.show');
    Route::delete('/kick/users/{kickUser}/ban', [UserController::class, 'unban'])
        ->name('kick.users.unban');

    // Moderation actions.
    Route::get('/kick/moderation', [ModerationController::class, 'index'])
        ->name('kick.moderation.index');
    Route::post('/kick/moderation/ban', [ModerationController::class, 'ban'])
        ->name('kick.moderation.ban');
    Route::delete('/kick/moderation/ban', [ModerationController::class, 'unban'])
        ->name('kick.moderation.unban');
    Route::delete('/kick/moderation/message', [ModerationController::class, 'deleteMessage'])
        ->middleware('throttle:30,1')
        ->name('kick.moderation.message');
});
