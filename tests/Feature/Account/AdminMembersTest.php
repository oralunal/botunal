<?php

use App\Http\Requests\Kick\MemberPermissionUpdateRequest;
use App\Models\User;

test('a user without users.manage cannot view the members admin', function () {
    $this->actingAs(asUserWith([]))
        ->get(route('kick.members.index'))
        ->assertForbidden();
});

test('a user with users.manage sees the members list with registry', function () {
    $member = User::factory()->kickMember()->create([
        'name' => 'Ali Veli',
        'email' => 'ali@example.com',
    ]);
    $nonMember = User::factory()->create(['kick_user_id' => null]);

    $this->actingAs(asUserWith(['users.manage']))
        ->get(route('kick.members.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/Members')
            ->has('users.data')
            ->has('registry')
        );

    $response = $this->actingAs(asUserWith(['users.manage']))
        ->get(route('kick.members.index'));

    $ids = collect($response->viewData('page')['props']['users']['data'])
        ->pluck('id');

    expect($ids)->toContain($member->id)
        ->and($ids)->not->toContain($nonMember->id);
});

test('the super admin sees their own row flagged as super admin', function () {
    $admin = asSuperAdmin();

    $response = $this->actingAs($admin)
        ->get(route('kick.members.index'))
        ->assertOk();

    $row = collect($response->viewData('page')['props']['users']['data'])
        ->firstWhere('id', $admin->id);

    expect($row)->not->toBeNull()
        ->and($row['is_super_admin'])->toBeTrue();
});

test('a user with users.manage can sync a member\'s permissions', function () {
    $actor = asUserWith(['users.manage']);
    $member = User::factory()->kickMember()->create();

    $this->actingAs($actor)
        ->from(route('kick.members.index'))
        ->patch(route('kick.members.update', $member), [
            'abilities' => ['wiki.view', 'wiki.create'],
        ])
        ->assertRedirect(route('kick.members.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Yetkiler güncellendi.',
        ]);

    $fresh = $member->fresh();
    expect($fresh->permissions->pluck('ability')->sort()->values()->all())
        ->toBe(['wiki.create', 'wiki.view'])
        ->and($fresh->can('wiki.create'))->toBeTrue()
        ->and($fresh->can('wiki.delete'))->toBeFalse();

    $this->actingAs(asUserWith(['users.manage']))
        ->from(route('kick.members.index'))
        ->patch(route('kick.members.update', $member), [
            'abilities' => ['wiki.view'],
        ])
        ->assertRedirect(route('kick.members.index'));

    expect($member->fresh()->permissions->pluck('ability')->all())
        ->toBe(['wiki.view']);
});

test('the super admin row cannot have its permissions changed', function () {
    $admin = asSuperAdmin();

    $this->actingAs(asUserWith(['users.manage']))
        ->patch(route('kick.members.update', $admin), [
            'abilities' => ['wiki.view'],
        ])
        ->assertForbidden();

    expect($admin->fresh()->permissions()->count())->toBe(0);
});

test('an invalid ability is rejected and leaves permissions unchanged', function () {
    $actor = asUserWith(['users.manage']);
    $member = User::factory()->kickMember()->create();
    $member->syncPermissions(['wiki.view']);

    $this->actingAs($actor)
        ->from(route('kick.members.index'))
        ->patch(route('kick.members.update', $member), [
            'abilities' => ['not.real'],
        ])
        ->assertSessionHasErrors('abilities.0');

    expect($member->fresh()->permissions->pluck('ability')->all())
        ->toBe(['wiki.view']);
});

test('updating member permissions requires users.manage', function () {
    $actor = asUserWith([]);
    $member = User::factory()->kickMember()->create();

    $this->actingAs($actor)
        ->patch(route('kick.members.update', $member), [
            'abilities' => ['wiki.view'],
        ])
        ->assertForbidden();

    expect($member->fresh()->permissions()->count())->toBe(0);
});

test('the super admin bypasses route gating and can sync a member', function () {
    $admin = asSuperAdmin();
    $member = User::factory()->kickMember()->create();

    $this->actingAs($admin)
        ->from(route('kick.members.index'))
        ->patch(route('kick.members.update', $member), [
            'abilities' => ['wiki.view'],
        ])
        ->assertRedirect(route('kick.members.index'));

    expect($member->fresh()->permissions->pluck('ability')->all())
        ->toBe(['wiki.view']);
});

test('MemberPermissionUpdateRequest authorize reflects the users.manage ability', function () {
    $request = new MemberPermissionUpdateRequest;

    $request->setUserResolver(fn () => asUserWith([]));
    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => asUserWith(['users.manage']));
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn (): User => asSuperAdmin());
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn () => null);
    expect($request->authorize())->toBeFalse();
});
