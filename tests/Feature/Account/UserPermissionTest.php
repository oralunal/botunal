<?php

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A regular (non super-admin) user. The first user created under
 * RefreshDatabase has id 1, which isSuperAdmin() treats as the super
 * administrator, so a regular user must have a non-1 id.
 */
function regularUser(): User
{
    return User::factory()->create(['id' => 2]);
}

test('granting a permission adds a row', function () {
    $user = regularUser();

    $user->grantPermission('wiki.view');

    $this->assertDatabaseHas('user_permissions', [
        'user_id' => $user->id,
        'ability' => 'wiki.view',
    ]);
});

test('granting the same permission twice does not duplicate', function () {
    $user = regularUser();

    $user->grantPermission('wiki.view');
    $user->grantPermission('wiki.view');

    expect($user->permissions()->where('ability', 'wiki.view')->count())->toBe(1);
});

test('revoking a permission removes it', function () {
    $user = regularUser();
    $user->grantPermission('wiki.view');

    $user->revokePermission('wiki.view');

    $this->assertDatabaseMissing('user_permissions', [
        'user_id' => $user->id,
        'ability' => 'wiki.view',
    ]);
});

test('hasPermission is false when not granted and true when granted', function () {
    $user = regularUser();

    expect($user->hasPermission('wiki.view'))->toBeFalse();

    $user->grantPermission('wiki.view');

    expect($user->hasPermission('wiki.view'))->toBeTrue();
});

test('the super administrator has every permission without rows', function () {
    $superAdmin = User::factory()->create(['id' => 1]);

    expect($superAdmin->isSuperAdmin())->toBeTrue();
    expect($superAdmin->hasPermission('anything'))->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(0);
});

test('syncPermissions replaces the permission set', function () {
    $user = regularUser();

    $user->syncPermissions(['a', 'b']);

    expect($user->permissions()->pluck('ability')->sort()->values()->all())
        ->toBe(['a', 'b']);

    $user->syncPermissions(['b', 'c']);

    expect($user->fresh()->permissions()->pluck('ability')->sort()->values()->all())
        ->toBe(['b', 'c']);
});

test('syncPermissions is idempotent with the same set', function () {
    $user = regularUser();

    $user->syncPermissions(['a', 'b']);
    $user->syncPermissions(['a', 'b']);

    expect($user->fresh()->permissions()->pluck('ability')->sort()->values()->all())
        ->toBe(['a', 'b']);
    expect($user->permissions()->count())->toBe(2);
});

test('the user_id and ability columns enforce a unique constraint', function () {
    $user = regularUser();
    UserPermission::factory()->create(['user_id' => $user->id, 'ability' => 'wiki.view']);

    expect(fn () => UserPermission::factory()->create([
        'user_id' => $user->id,
        'ability' => 'wiki.view',
    ]))->toThrow(UniqueConstraintViolationException::class);
});
