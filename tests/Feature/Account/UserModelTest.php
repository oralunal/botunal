<?php

use App\Models\User;

test('isSuperAdmin is true only for the user with id 1', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    expect($first->id)->toBe(1);
    expect($first->isSuperAdmin())->toBeTrue();
    expect($second->isSuperAdmin())->toBeFalse();
});

test('isProfileComplete is true when first name, last name and email are present', function () {
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    expect($user->isProfileComplete())->toBeTrue();
});

test('isProfileComplete is false when first name is missing', function () {
    $user = User::factory()->create([
        'first_name' => null,
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    expect($user->isProfileComplete())->toBeFalse();
});

test('isProfileComplete is false when last name is missing', function () {
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => null,
        'email' => 'ada@example.com',
    ]);

    expect($user->isProfileComplete())->toBeFalse();
});

test('isProfileComplete is false when email is missing', function () {
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => '',
    ]);

    expect($user->isProfileComplete())->toBeFalse();
});

test('the kickMember factory state produces a kick member with a null password', function () {
    $user = User::factory()->kickMember()->create();

    expect($user->kick_user_id)->not->toBeNull();
    expect($user->kick_username)->not->toBeNull();
    expect($user->first_name)->not->toBeNull();
    expect($user->last_name)->not->toBeNull();
    expect($user->password)->toBeNull();
});

test('the incompleteProfile factory state yields an incomplete profile', function () {
    $user = User::factory()->incompleteProfile()->create();

    expect($user->first_name)->toBeNull();
    expect($user->last_name)->toBeNull();
    expect($user->isProfileComplete())->toBeFalse();
});
