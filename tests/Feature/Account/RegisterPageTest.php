<?php

use App\Models\User;

test('the register landing page can be rendered by a guest', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Register'));
});

test('an authenticated user is redirected away from the register page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('register'));

    expect($response->getStatusCode())->not->toBe(200);
    $response->assertRedirect();
});
