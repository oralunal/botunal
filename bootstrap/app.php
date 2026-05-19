<?php

use App\Http\Middleware\EnsurePasswordAccount;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyKickSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'kick.signature' => VerifyKickSignature::class,
            'permission' => EnsurePermission::class,
            'profile.complete' => EnsureProfileComplete::class,
            'password.account' => EnsurePasswordAccount::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'kick/webhook',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('kick:refresh-tokens')->everyFiveMinutes();
        $schedule->command('kick:sync-subscriptions')->hourly();
        $schedule->command('kick:run-timers')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
