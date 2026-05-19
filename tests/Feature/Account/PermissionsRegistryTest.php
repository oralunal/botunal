<?php

use App\Support\Permissions;

test('all() is non-empty and contains no duplicates', function () {
    $all = Permissions::all();

    expect($all)->not->toBeEmpty();
    expect($all)->toHaveCount(count(array_unique($all)));
});

test('all() and the flattened groups() are the exact same set', function () {
    $fromGroups = [];

    foreach (Permissions::groups() as $items) {
        foreach (array_keys($items) as $ability) {
            $fromGroups[] = $ability;
        }
    }

    sort($fromGroups);
    $all = Permissions::all();
    sort($all);

    expect($all)->toBe($fromGroups);
});

test('isValid() reflects membership in all()', function () {
    expect(Permissions::isValid('wiki.create'))->toBeTrue();
    expect(Permissions::isValid('does.not.exist'))->toBeFalse();
    expect(Permissions::isValid(''))->toBeFalse();
});

test('the load-bearing abilities exist', function () {
    expect(Permissions::all())->toContain(
        'wiki.view',
        'wiki.create',
        'wiki.edit',
        'wiki.delete',
        'users.manage',
        'member-messages.view',
        'dashboard.view',
    );
});
