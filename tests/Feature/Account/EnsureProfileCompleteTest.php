<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an incomplete Kick member is redirected to account.edit', function () {
    // Reserve id 1 so the member under test is not the super admin.
    asSuperAdmin();

    $member = User::factory()->create([
        'kick_user_id' => 900,
        'first_name' => null,
        'last_name' => null,
        'email' => 'x@e.com',
    ]);
    $member->grantPermission('dashboard.view');

    expect($member->isProfileComplete())->toBeFalse();

    $this->actingAs($member)
        ->get(route('kick.dashboard'))
        ->assertRedirect(route('account.edit'));
});

test('an incomplete Kick member can still reach the exempt account.edit route', function () {
    asSuperAdmin();

    $member = User::factory()->create([
        'kick_user_id' => 900,
        'first_name' => null,
        'last_name' => null,
        'email' => 'x@e.com',
    ]);

    $this->actingAs($member)
        ->get(route('account.edit'))
        ->assertOk();
});

test('a complete Kick member passes through to the dashboard', function () {
    asSuperAdmin();

    $member = User::factory()->create([
        'kick_user_id' => 901,
        'first_name' => 'A',
        'last_name' => 'B',
        'email' => 'ab@e.com',
    ]);
    $member->grantPermission('dashboard.view');

    expect($member->isProfileComplete())->toBeTrue();

    $this->actingAs($member)
        ->get(route('kick.dashboard'))
        ->assertOk();
});

test('a non-Kick user with an incomplete profile is not redirected on account.edit', function () {
    asSuperAdmin();

    $user = User::factory()->create([
        'kick_user_id' => null,
        'first_name' => null,
        'last_name' => null,
        'email' => 'plain@e.com',
    ]);

    expect($user->isProfileComplete())->toBeFalse();

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk();
});

test('the super admin (non-member) is unaffected by profile.complete on kick routes', function () {
    $admin = asSuperAdmin();

    expect($admin->kick_user_id)->toBeNull()
        ->and($admin->isProfileComplete())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('kick.dashboard'))
        ->assertOk();
});

test('a guest is redirected to login by auth, not profile.complete', function () {
    $this->get(route('account.edit'))
        ->assertRedirect(route('login'));
});
