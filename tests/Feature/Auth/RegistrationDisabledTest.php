<?php

use Laravel\Fortify\Features;

test('the registration feature is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the Fortify registration POST route is not available', function () {
    // Fortify's built-in registration endpoint must stay disabled. The
    // app exposes its own GET /register page for member (Kick) sign-up,
    // which is not handled by Fortify, so POST /register is unhandled
    // (405: the path exists for GET only, no Fortify POST handler).
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertMethodNotAllowed();
});

test('the welcome page reports that sign-ups are closed', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('canRegister', false)
    );
});
