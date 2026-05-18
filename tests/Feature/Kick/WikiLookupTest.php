<?php

use App\Models\WikiEntry;
use App\Services\Kick\WikiLookup;
use App\Services\Kick\WikiText;
use Illuminate\Support\Facades\Cache;

test('a wiki entry has aliases and an enabled scope', function () {
    $entry = WikiEntry::factory()->create(['type' => 'perk', 'name_en' => 'Dead Hard']);
    $entry->aliases()->create(['alias' => 'DH', 'alias_norm' => 'dh']);
    WikiEntry::factory()->create(['name_en' => 'Hidden', 'is_enabled' => false]);

    expect($entry->aliases)->toHaveCount(1)
        ->and($entry->aliases->first()->alias)->toBe('DH')
        ->and(WikiEntry::enabled()->count())->toBe(1);
});

function lookup(): WikiLookup
{
    return app(WikiLookup::class);
}

function seedDeadHard(): WikiEntry
{
    $entry = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard',
        'name_tr' => 'Kum Torbası',
        'slug' => 'perk david king dead hard',
        'description_tr' => 'Yaralıyken bir saniye boyunca dayanıklılık kazanırsın.',
    ]);
    foreach (['Dead Hard', 'Kum Torbası', 'DH'] as $a) {
        $entry->aliases()->create(['alias' => $a, 'alias_norm' => WikiText::normalize($a)]);
    }

    return $entry;
}

test('empty term returns usage', function () {
    expect(lookup()->answer(''))->toContain('Kullanım: !wiki');
});

test('exact English, Turkish and alias hits resolve and format a perk', function () {
    seedDeadHard();

    $expected = 'Kum Torbası (Dead Hard) — Kurban: David King · Açıklama: Yaralıyken bir saniye boyunca dayanıklılık kazanırsın.';

    expect(lookup()->answer('dead hard'))->toBe($expected)
        ->and(lookup()->answer('KUM TORBASI'))->toBe($expected)
        ->and(lookup()->answer('kum torbasi'))->toBe($expected)
        ->and(lookup()->answer('dh'))->toBe($expected);
});

test('disabled entries are not returned', function () {
    $e = seedDeadHard();
    $e->update(['is_enabled' => false]);
    Cache::forget('wiki:index');

    expect(lookup()->answer('dead hard'))->toContain('bulunamadı');
});

test('power and term formats differ', function () {
    WikiEntry::factory()->create([
        'type' => 'power', 'name_en' => 'Blink', 'name_tr' => 'Işınlanma',
        'owner' => 'The Nurse', 'role' => 'killer', 'slug' => 'power the nurse blink',
        'description_tr' => 'Kısa mesafe ışınlanır.',
    ]);
    WikiEntry::factory()->create([
        'type' => 'term', 'name_en' => 'Gen Rush', 'name_tr' => null,
        'owner' => null, 'role' => null, 'slug' => 'term gen rush',
        'description_tr' => 'Jeneratörleri çok hızlı tamamlama.',
    ]);

    expect(lookup()->answer('Blink'))
        ->toBe('Işınlanma (Blink) — Katil: The Nurse [Güç] · Açıklama: Kısa mesafe ışınlanır.')
        ->and(lookup()->answer('gen rush'))
        ->toBe('Gen Rush — Terim · Açıklama: Jeneratörleri çok hızlı tamamlama.');
});

test('not found with near matches lists exactly three names', function () {
    seedDeadHard();
    // These slugs contain "deadh" so str_contains scores them distance=1, within threshold
    WikiEntry::factory()->create(['name_en' => 'Deadhook', 'name_tr' => null, 'slug' => 'perk x deadhook']);
    WikiEntry::factory()->create(['name_en' => 'Deadhaze', 'name_tr' => null, 'slug' => 'perk x deadhaze']);
    WikiEntry::factory()->create(['name_en' => 'Unrelated Skill', 'name_tr' => null, 'slug' => 'perk y unrelated skill']);
    Cache::forget('wiki:index');

    $answer = lookup()->answer('deadh');

    expect($answer)->toContain('bulunamadı')
        ->and($answer)->toContain('Şunları deneyin:');
    expect(substr_count($answer, ','))->toBe(2); // exactly 3 suggestions
});

test('not found with nothing close returns usage', function () {
    seedDeadHard();
    Cache::forget('wiki:index');

    expect(lookup()->answer('zzzzzzzzplugh'))
        ->toContain('bulunamadı')
        ->toContain('Kullanım: !wiki');
});

test('long descriptions are truncated to keep the message <= 480 chars', function () {
    WikiEntry::factory()->create([
        'type' => 'perk', 'name_en' => 'Verbose', 'name_tr' => 'Uzun',
        'owner' => 'Someone', 'role' => 'survivor', 'slug' => 'perk someone verbose',
        'description_tr' => str_repeat('uzun açıklama ', 80),
    ]);

    $answer = lookup()->answer('Verbose');

    expect(mb_strlen($answer))->toBeLessThanOrEqual(480)
        ->and($answer)->toContain('Uzun (Verbose) — Kurban: Someone')
        ->and($answer)->toEndWith('…');
});
