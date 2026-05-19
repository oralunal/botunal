<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\MemberMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'profile.complete'])->group(function () {
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');

    Route::get('/account/messages', [MemberMessageController::class, 'index'])->name('account.messages.index');
    Route::post('/account/messages', [MemberMessageController::class, 'store'])->name('account.messages.store');
});
