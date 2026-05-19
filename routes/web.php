<?php

use App\Http\Controllers\Auth\KickAuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::inertia('register', 'auth/Register')->middleware('guest')->name('register');

Route::middleware('guest')->get('/auth/kick/redirect', [KickAuthController::class, 'redirect'])
    ->name('auth.kick.redirect');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/account.php';
require __DIR__.'/kick.php';
