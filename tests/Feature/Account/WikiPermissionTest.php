<?php

use App\Http\Requests\Kick\WikiStoreRequest;
use App\Http\Requests\Kick\WikiUpdateRequest;
use App\Models\User;
use App\Models\WikiEntry;

/**
 * @return array<string, mixed>
 */
function validWikiPayload(): array
{
    return [
        'type' => 'perk', 'name_en' => 'Sprint Burst', 'name_tr' => 'Sprint',
        'owner' => 'Meg Thomas', 'role' => 'survivor', 'description_tr' => 'Hız.',
        'is_enabled' => true, 'aliases' => ['Sprint', 'SB'],
    ];
}

test('viewer can read the wiki index but cannot create', function () {
    $this->actingAs(asUserWith(['wiki.view']));

    $this->get(route('kick.wiki.index'))->assertOk();

    $this->post(route('kick.wiki.store'), validWikiPayload())->assertForbidden();

    expect(WikiEntry::count())->toBe(0);
});

test('a user with wiki.create can store an entry', function () {
    $this->actingAs(asUserWith(['wiki.view', 'wiki.create']));

    $this->post(route('kick.wiki.store'), validWikiPayload())
        ->assertRedirect(route('kick.wiki.index'));

    expect(WikiEntry::firstWhere('name_en', 'Sprint Burst'))->not->toBeNull();
});

test('a viewer cannot update but a wiki.edit user can', function () {
    $entry = WikiEntry::factory()->create(['name_en' => 'Old', 'slug' => 'perk x old']);

    $payload = [
        'type' => 'perk', 'name_en' => 'Old', 'name_tr' => 'Yeni',
        'description_tr' => 'Güncellendi.', 'is_enabled' => true, 'aliases' => [],
    ];

    $this->actingAs(asUserWith(['wiki.view']));
    $this->put(route('kick.wiki.update', $entry), $payload)->assertForbidden();

    $this->actingAs(asUserWith(['wiki.view', 'wiki.edit']));
    $this->put(route('kick.wiki.update', $entry), $payload)
        ->assertRedirect(route('kick.wiki.index'));

    expect($entry->fresh()->name_tr)->toBe('Yeni');
});

test('a viewer cannot delete but a wiki.delete user can', function () {
    $entry = WikiEntry::factory()->create(['slug' => 'perk x gone']);

    $this->actingAs(asUserWith(['wiki.view']));
    $this->delete(route('kick.wiki.destroy', $entry))->assertForbidden();
    expect(WikiEntry::count())->toBe(1);

    $this->actingAs(asUserWith(['wiki.view', 'wiki.delete']));
    $this->delete(route('kick.wiki.destroy', $entry))
        ->assertRedirect(route('kick.wiki.index'));
    expect(WikiEntry::count())->toBe(0);
});

test('the super admin can store, update and delete', function () {
    $this->actingAs(asSuperAdmin());

    $this->post(route('kick.wiki.store'), validWikiPayload())
        ->assertRedirect(route('kick.wiki.index'));

    $entry = WikiEntry::firstWhere('name_en', 'Sprint Burst');
    expect($entry)->not->toBeNull();

    $this->put(route('kick.wiki.update', $entry), [
        'type' => 'perk', 'name_en' => 'Sprint Burst', 'name_tr' => 'Değişti',
        'description_tr' => 'Güncel.', 'is_enabled' => true, 'aliases' => [],
    ])->assertRedirect(route('kick.wiki.index'));
    expect($entry->fresh()->name_tr)->toBe('Değişti');

    $this->delete(route('kick.wiki.destroy', $entry))
        ->assertRedirect(route('kick.wiki.index'));
    expect(WikiEntry::count())->toBe(0);
});

test('WikiStoreRequest authorize reflects the wiki.create ability', function () {
    $request = new WikiStoreRequest;

    $request->setUserResolver(fn () => asUserWith(['wiki.view']));
    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => asUserWith(['wiki.create']));
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn () => null);
    expect($request->authorize())->toBeFalse();
});

test('WikiUpdateRequest authorize reflects the wiki.edit ability', function () {
    $request = new WikiUpdateRequest;

    $request->setUserResolver(fn () => asUserWith(['wiki.view']));
    expect($request->authorize())->toBeFalse();

    $request->setUserResolver(fn () => asUserWith(['wiki.edit']));
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn () => null);
    expect($request->authorize())->toBeFalse();
});

test('the super admin bypasses both FormRequest authorize checks', function () {
    $admin = asSuperAdmin();

    $store = new WikiStoreRequest;
    $store->setUserResolver(fn (): User => $admin);
    expect($store->authorize())->toBeTrue();

    $update = new WikiUpdateRequest;
    $update->setUserResolver(fn (): User => $admin);
    expect($update->authorize())->toBeTrue();
});
