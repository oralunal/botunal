<?php

use App\Models\User;

test('guests are redirected to login from the account profile page', function () {
    $this->get(route('account.edit'))->assertRedirect(route('login'));
});

test('account profile page is displayed for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('account/Profile'));
});

test('account profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('account.update'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $user->email,
            'phone' => '+90 555 123 4567',
            'instagram' => 'janedoe',
            'twitter' => 'janedoe_x',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.edit'));

    $user->refresh();

    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Doe');
    expect($user->phone)->toBe('+90 555 123 4567');
    expect($user->instagram)->toBe('janedoe');
    expect($user->twitter)->toBe('janedoe_x');
});

test('a success toast is flashed after a profile update', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('account.update'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $user->email,
        ])
        ->assertRedirect(route('account.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Profil güncellendi.',
        ]);
});

test('account profile update requires a first name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account.edit'))
        ->patch(route('account.update'), [
            'last_name' => 'Doe',
            'email' => $user->email,
        ])
        ->assertSessionHasErrors('first_name');
});

test('changing the email address clears the verification timestamp', function () {
    $user = User::factory()->create();

    expect($user->email_verified_at)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('account.update'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'changed-'.$user->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.edit'));

    expect($user->refresh()->email_verified_at)->toBeNull();
});

test('the email address must be unique across other users', function () {
    $other = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account.edit'))
        ->patch(route('account.update'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $other->email,
        ])
        ->assertSessionHasErrors('email');
});
