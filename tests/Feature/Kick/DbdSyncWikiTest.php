<?php

use App\Models\Command;
use App\Models\WikiEntry;

/**
 * Writes a tiny fixture dataset to a temp dir and points the command at it
 * via --path, so the test never depends on the large real datasets.
 *
 * @param  array<int, array<string, mixed>>  $tier1
 * @param  array<int, array<string, mixed>>  $tier2
 */
function writeDbdFixture(array $tier1, array $tier2 = []): string
{
    $dir = sys_get_temp_dir().'/dbd-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/tier1.json', json_encode($tier1));
    file_put_contents($dir.'/tier2.json', json_encode($tier2));

    return $dir;
}

test('sync seeds entries, aliases and the wiki command', function () {
    $dir = writeDbdFixture([
        [
            'type' => 'perk', 'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası',
            'owner' => 'David King', 'role' => 'survivor',
            'description_tr' => 'Dayanıklılık.', 'description_en' => 'Endurance.',
            'source_url' => 'https://example.test/dh', 'aliases' => ['DH'],
        ],
    ]);

    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])
        ->assertSuccessful();

    $entry = WikiEntry::firstWhere('name_en', 'Dead Hard');
    expect($entry->slug)->toBe('perk david king dead hard')
        ->and($entry->is_curated)->toBeFalse()
        ->and($entry->aliases()->pluck('alias_norm')->sort()->values()->all())
        ->toEqual(['dead hard', 'dh', 'kum torbasi'])
        ->and(Command::where('name', 'wiki')->where('handler', 'wiki')->exists())->toBeTrue();
});

test('sync is idempotent', function () {
    $dir = writeDbdFixture([
        ['type' => 'term', 'name_en' => 'Tunnel', 'name_tr' => 'Tünel', 'aliases' => []],
    ]);

    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])->assertSuccessful();
    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])->assertSuccessful();

    expect(WikiEntry::where('name_en', 'Tunnel')->count())->toBe(1)
        ->and(WikiEntry::firstWhere('name_en', 'Tunnel')->aliases()->count())->toBe(2);
});

test('sync does not overwrite curated name/description but still adds new aliases', function () {
    $dir = writeDbdFixture([
        [
            'type' => 'perk', 'name_en' => 'Adrenaline', 'name_tr' => 'Wiki TR',
            'description_tr' => 'Wiki açıklaması.', 'aliases' => ['adr'],
        ],
    ]);
    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '1'])->assertSuccessful();

    $entry = WikiEntry::firstWhere('name_en', 'Adrenaline');
    $entry->update(['name_tr' => 'Admin TR', 'description_tr' => 'Admin yazdı.', 'is_curated' => true]);

    $dir2 = writeDbdFixture([
        [
            'type' => 'perk', 'name_en' => 'Adrenaline', 'name_tr' => 'Değişti',
            'description_tr' => 'Değişti.', 'aliases' => ['adr', 'adre'],
        ],
    ]);
    $this->artisan('dbd:sync-wiki', ['--path' => $dir2, '--tier' => '1'])->assertSuccessful();

    $entry->refresh();
    expect($entry->name_tr)->toBe('Admin TR')
        ->and($entry->description_tr)->toBe('Admin yazdı.')
        ->and($entry->aliases()->pluck('alias_norm')->sort()->values()->all())
        ->toContain('adre');
});

test('tier option filters which dataset loads', function () {
    $dir = writeDbdFixture(
        [['type' => 'perk', 'name_en' => 'T1 Perk', 'aliases' => []]],
        [['type' => 'addon', 'name_en' => 'T2 Addon', 'owner' => 'The Trapper', 'aliases' => []]],
    );

    $this->artisan('dbd:sync-wiki', ['--path' => $dir, '--tier' => '2'])->assertSuccessful();

    expect(WikiEntry::where('name_en', 'T2 Addon')->exists())->toBeTrue()
        ->and(WikiEntry::where('name_en', 'T1 Perk')->exists())->toBeFalse();
});
