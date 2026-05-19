<?php

test('asSuperAdmin returns a super admin with a complete profile', function () {
    $user = asSuperAdmin();

    expect($user->isSuperAdmin())->toBeTrue();
    expect($user->isProfileComplete())->toBeTrue();
});

test('asUserWith returns a non super admin with only the granted abilities', function () {
    $user = asUserWith(['wiki.view']);

    expect($user->isSuperAdmin())->toBeFalse();
    expect($user->hasPermission('wiki.view'))->toBeTrue();
    expect($user->hasPermission('wiki.delete'))->toBeFalse();
});

test('asSuperAdmin guarantees a complete profile even after asUserWith created the id 1 sentinel', function () {
    asUserWith(['wiki.view']);

    $superAdmin = asSuperAdmin();

    expect($superAdmin->isSuperAdmin())->toBeTrue();
    expect($superAdmin->isProfileComplete())->toBeTrue();
});
