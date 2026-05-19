<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * A regular (non super-admin) user. The first user created under
 * RefreshDatabase has id 1, which isSuperAdmin() treats as the super
 * administrator, so a regular user must have a non-1 id.
 */
function gateRegularUser(): User
{
    return User::factory()->create(['id' => 2]);
}

test('a non super-admin cannot use an ability until it is granted', function () {
    $user = gateRegularUser();

    expect($user->can('wiki.create'))->toBeFalse();
    expect(Gate::forUser($user)->allows('wiki.create'))->toBeFalse();

    $user->grantPermission('wiki.create');

    expect($user->fresh()->can('wiki.create'))->toBeTrue();
    expect(Gate::forUser($user->fresh())->allows('wiki.create'))->toBeTrue();
});

test('a granted ability does not leak to other abilities', function () {
    $user = gateRegularUser();
    $user->grantPermission('wiki.create');

    expect($user->can('wiki.create'))->toBeTrue();
    expect($user->can('wiki.delete'))->toBeFalse();
    expect($user->can('users.manage'))->toBeFalse();
});

test('the super administrator passes every ability without permission rows', function () {
    $superAdmin = User::factory()->create(['id' => 1]);

    expect($superAdmin->isSuperAdmin())->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(0);
    expect($superAdmin->can('wiki.create'))->toBeTrue();
    expect($superAdmin->can('users.manage'))->toBeTrue();
    expect($superAdmin->can('dashboard.view'))->toBeTrue();
});

test('the permission middleware blocks users without the ability', function () {
    Route::middleware(['web', 'permission:wiki.view'])
        ->get('/__perm_test', fn () => 'ok');

    $user = gateRegularUser();

    $this->actingAs($user)
        ->get('/__perm_test')
        ->assertStatus(403);
});

test('the permission middleware allows users with the ability', function () {
    Route::middleware(['web', 'permission:wiki.view'])
        ->get('/__perm_test', fn () => 'ok');

    $user = gateRegularUser();
    $user->grantPermission('wiki.view');

    $this->actingAs($user->fresh())
        ->get('/__perm_test')
        ->assertOk()
        ->assertSee('ok');
});

test('the permission middleware allows the super administrator', function () {
    Route::middleware(['web', 'permission:wiki.view'])
        ->get('/__perm_test', fn () => 'ok');

    $superAdmin = User::factory()->create(['id' => 1]);

    $this->actingAs($superAdmin)
        ->get('/__perm_test')
        ->assertOk()
        ->assertSee('ok');
});
