<?php

use App\Services\Kick\WikiText;

test('normalize folds Turkish diacritics and casing', function () {
    expect(WikiText::normalize('Kum Torbası'))->toBe('kum torbasi')
        ->and(WikiText::normalize('KUM TORBASI'))->toBe('kum torbasi')
        ->and(WikiText::normalize('İŞÇĞÖÜ'))->toBe('iscgou')
        ->and(WikiText::normalize('Dead Hard'))->toBe('dead hard');
});

test('normalize collapses whitespace and strips punctuation', function () {
    expect(WikiText::normalize("  Dead   Man's  Switch!! "))->toBe('dead mans switch')
        ->and(WikiText::normalize('Hex: No One Escapes Death'))->toBe('hex no one escapes death')
        ->and(WikiText::normalize(''))->toBe('');
});

test('slug joins type owner and name deterministically', function () {
    expect(WikiText::slug('perk', 'David King', 'Dead Hard'))
        ->toBe('perk david king dead hard')
        ->and(WikiText::slug('term', null, 'Gen Rush'))->toBe('term gen rush');
});
