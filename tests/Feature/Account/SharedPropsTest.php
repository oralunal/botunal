<?php

use App\Support\Permissions;

test('a permissioned user sees their own abilities and is not a super admin', function () {
    $user = asUserWith(['wiki.view', 'wiki.create']);

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_super_admin', false)
            ->where('auth.permissions', function ($perms) {
                $perms = collect($perms)->all();

                return in_array('wiki.view', $perms, true)
                    && in_array('wiki.create', $perms, true)
                    && ! in_array('wiki.delete', $perms, true);
            }));
});

test('the super admin sees the full permission set and is flagged as super admin', function () {
    $this->actingAs(asSuperAdmin())
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_super_admin', true)
            ->where('auth.permissions', function ($perms) {
                $perms = collect($perms)->all();

                return in_array('users.manage', $perms, true)
                    && in_array('wiki.delete', $perms, true)
                    && in_array('dashboard.view', $perms, true)
                    && count($perms) === count(Permissions::all());
            }));
});

test('a user with no permissions sees an empty permission list', function () {
    $this->actingAs(asUserWith([]))
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_super_admin', false)
            ->where('auth.permissions', []));
});

test('a guest sees no auth user, no permissions and is not a super admin', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user', null)
            ->where('auth.is_super_admin', false)
            ->where('auth.permissions', []));
});
