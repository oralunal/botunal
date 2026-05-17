<?php

use Laravel\Fortify\Features;

test('the registration feature is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the register routes are not available', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

test('the welcome page reports that sign-ups are closed', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->where('canRegister', false)
    );
});
