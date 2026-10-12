<?php

use App\Models\Timer;

test('timers can be reordered', function () {
    [$first, $second, $third] = Timer::factory()->count(3)->sequence(
        ['position' => 1],
        ['position' => 2],
        ['position' => 3],
    )->create();

    $this->actingAs(asSuperAdmin())
        ->put(route('kick.timer-order.update'), ['ids' => [$third->id, $first->id, $second->id]])
        ->assertRedirect(route('kick.timers.index'));

    expect(Timer::query()->inRotationOrder()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id]);
});

test('the new order must list every timer exactly once', function (Closure $ids, string $errorKey) {
    [$first, $second, $third] = Timer::factory()->count(3)->sequence(
        ['position' => 1],
        ['position' => 2],
        ['position' => 3],
    )->create();

    $this->actingAs(asSuperAdmin())
        ->put(route('kick.timer-order.update'), ['ids' => $ids($first, $second, $third)])
        ->assertSessionHasErrors($errorKey);

    expect(Timer::query()->inRotationOrder()->pluck('id')->all())->toBe([$first->id, $second->id, $third->id]);
})->with([
    'a timer is missing' => [fn (Timer $first, Timer $second): array => [$second->id, $first->id], 'ids'],
    'a timer is repeated' => [fn (Timer $first, Timer $second): array => [$second->id, $second->id, $first->id], 'ids.0'],
    'an unknown timer is listed' => [fn (Timer $first, Timer $second): array => [$second->id, $first->id, 999999], 'ids.2'],
]);

test('reordering timers requires the timers permission', function () {
    $timer = Timer::factory()->create(['position' => 1]);

    $this->actingAs(asUserWith(['commands.manage']))
        ->put(route('kick.timer-order.update'), ['ids' => [$timer->id]])
        ->assertForbidden();
});
