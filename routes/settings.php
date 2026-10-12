<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // GET-only instead of Route::redirect(): Wayfinder cannot type the QUERY verb that `any` routes now include.
    Route::get('settings', RedirectController::class)
        ->defaults('destination', '/settings/profile')
        ->defaults('status', 302);

    Route::get('settings/profile', [ProfileController::class, 'edit'])
        ->middleware('redirect-kick:account.edit')
        ->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('redirect-kick:account.edit')
        ->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware('password.account')
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware(['password.account', 'throttle:6,1'])
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')
        ->middleware('redirect-kick:account.appearance')
        ->name('appearance.edit');
});
