<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('Kick member can visit the account appearance page', function () {
    $user = User::factory()->kickMember()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('account.appearance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('account/Appearance'));
});

test('Kick member is redirected from /settings/profile to /account', function () {
    $user = User::factory()->kickMember()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('account.edit'));
});

test('Kick member is redirected from /settings/appearance to /account/appearance', function () {
    $user = User::factory()->kickMember()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertRedirect(route('account.appearance'));
});

test('password admin can still visit /settings/profile and /settings/appearance', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
    $this->actingAs($admin)->get(route('appearance.edit'))->assertOk();
});
