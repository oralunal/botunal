<?php

use App\Models\User;
use App\Models\WikiAlias;
use App\Models\WikiEntry;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the wiki index requires authentication', function () {
    auth()->logout();
    $this->get(route('kick.wiki.index'))->assertRedirect(route('login'));
});

test('index lists, filters by type and searches name/alias/owner', function () {
    $dh = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası', 'slug' => 'perk david king dead hard',
    ]);
    $dh->aliases()->create(['alias' => 'DH', 'alias_norm' => 'dh']);
    WikiEntry::factory()->create(['type' => 'term', 'name_en' => 'Tunnel', 'slug' => 'term tunnel']);

    $this->get(route('kick.wiki.index'))
        ->assertInertia(fn ($p) => $p->component('kick/Wiki')->has('entries.data', 2));

    $this->get(route('kick.wiki.index', ['type' => 'term']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 1)
            ->where('entries.data.0.name_en', 'Tunnel'));

    $this->get(route('kick.wiki.index', ['search' => 'kum torbasi']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 1)
            ->where('entries.data.0.name_en', 'Dead Hard'));

    $this->get(route('kick.wiki.index', ['search' => 'david']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 1));
});

test('index paginates at 50', function () {
    WikiEntry::factory()->count(55)->create();

    $this->get(route('kick.wiki.index'))
        ->assertInertia(fn ($p) => $p->has('entries.data', 50));
});

test('store creates an entry with normalized deduped aliases', function () {
    $this->post(route('kick.wiki.store'), [
        'type' => 'perk', 'name_en' => 'Sprint Burst', 'name_tr' => 'Sprint',
        'owner' => 'Meg Thomas', 'role' => 'survivor', 'description_tr' => 'Hız.',
        'is_enabled' => true, 'aliases' => ['Sprint', 'sprint', 'SB'],
    ])->assertRedirect(route('kick.wiki.index'));

    $entry = WikiEntry::firstWhere('name_en', 'Sprint Burst');
    expect($entry)->not->toBeNull()
        ->and($entry->slug)->toBe('perk meg thomas sprint burst')
        ->and($entry->is_curated)->toBeTrue()
        ->and($entry->aliases()->pluck('alias_norm')->sort()->values()->all())
        ->toEqual(['sb', 'sprint']); // 'Sprint' & 'sprint' dedupe
});

test('update edits the entry, marks it curated and resyncs aliases', function () {
    $entry = WikiEntry::factory()->create(['name_en' => 'Old', 'slug' => 'perk x old']);
    $entry->aliases()->create(['alias' => 'o', 'alias_norm' => 'o']);

    $this->put(route('kick.wiki.update', $entry), [
        'type' => 'perk', 'name_en' => 'Old', 'name_tr' => 'Yeni',
        'description_tr' => 'Güncellendi.', 'is_enabled' => true, 'aliases' => ['yeni'],
    ])->assertRedirect(route('kick.wiki.index'));

    $entry->refresh();
    expect($entry->name_tr)->toBe('Yeni')
        ->and($entry->is_curated)->toBeTrue()
        ->and($entry->aliases()->pluck('alias_norm')->all())->toEqual(['yeni']);
});

test('destroy removes the entry and cascades aliases', function () {
    $entry = WikiEntry::factory()->create(['slug' => 'perk x gone']);
    $entry->aliases()->create(['alias' => 'g', 'alias_norm' => 'g']);

    $this->delete(route('kick.wiki.destroy', $entry))->assertRedirect(route('kick.wiki.index'));

    expect(WikiEntry::count())->toBe(0)
        ->and(WikiAlias::count())->toBe(0);
});

test('update without resending owner keeps it in the slug', function () {
    $entry = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası', 'slug' => 'perk david king dead hard',
    ]);

    $this->put(route('kick.wiki.update', $entry), [
        'type' => 'perk', 'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası',
        'description_tr' => 'Güncel.', 'is_enabled' => true, 'aliases' => [],
        // owner intentionally omitted
    ])->assertRedirect(route('kick.wiki.index'));

    $entry->refresh();
    expect($entry->owner)->toBe('David King')
        ->and($entry->slug)->toBe('perk david king dead hard');
});

test('store validation rejects an invalid type', function () {
    $this->post(route('kick.wiki.store'), ['type' => 'bogus', 'name_en' => 'X'])
        ->assertSessionHasErrors('type');
});

test('search treats LIKE wildcards literally (no match-all)', function () {
    WikiEntry::factory()->count(3)->create();

    $this->get(route('kick.wiki.index', ['search' => '%']))
        ->assertInertia(fn ($p) => $p->has('entries.data', 0));
});
