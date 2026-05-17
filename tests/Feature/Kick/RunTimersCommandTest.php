<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\Timer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.kick.channel_slug', 'trolunal');
    Queue::fake();
});

test('timers fire when due and live', function () {
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create([
        'message' => 'Welcome to {channel}',
        'last_run_at' => now()->subHour(),
        'interval_seconds' => 600,
    ]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertPushed(SendChatMessageJob::class, function (SendChatMessageJob $job) {
        return $job->content === 'Welcome to trolunal';
    });
    expect(Timer::first()->last_run_at->isToday())->toBeTrue();
});

test('timers are skipped when offline and only_when_live', function () {
    Cache::put('kick:livestream:is_live', false);
    Timer::factory()->create([
        'only_when_live' => true,
        'last_run_at' => now()->subHour(),
    ]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('timers not yet due are skipped', function () {
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create([
        'interval_seconds' => 600,
        'last_run_at' => now()->subSeconds(60),
    ]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('disabled timers never fire', function () {
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->disabled()->create(['last_run_at' => now()->subDay()]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});
