<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\KickConnection;
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

test('the timer after the last one to run fires next once its own interval has passed', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['message' => 'first', 'interval_seconds' => 30, 'last_run_at' => now()->subSeconds(60)]);
    $second = Timer::factory()->create(['message' => 'second', 'interval_seconds' => 60, 'last_run_at' => now()->subHour()]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    expect(Queue::pushed(SendChatMessageJob::class)->pluck('content')->all())->toBe(['second']);
    $this->assertDatabaseHas('timers', ['id' => $second->id, 'last_run_at' => now()]);
});

test('the rotation wraps around to the first timer after the last one', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['message' => 'first', 'interval_seconds' => 30, 'last_run_at' => now()->subHours(2)]);
    Timer::factory()->create(['message' => 'second', 'interval_seconds' => 60, 'last_run_at' => now()->subSeconds(30)]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    expect(Queue::pushed(SendChatMessageJob::class)->pluck('content')->all())->toBe(['first']);
});

test('the next timer waits its interval after the previous timer message', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['interval_seconds' => 30, 'last_run_at' => now()->subSeconds(59)]);
    Timer::factory()->create(['interval_seconds' => 60, 'last_run_at' => now()->subHour()]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a timer that never ran waits its interval after it was created', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['interval_seconds' => 30, 'created_at' => now()->subSeconds(29)]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a new stream restarts the rotation from the first timer', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Cache::put('kick:livestream:started_at', now()->subSeconds(30)->toIso8601String());
    Timer::factory()->create(['message' => 'first', 'interval_seconds' => 30, 'last_run_at' => now()->subHour()]);
    Timer::factory()->create(['message' => 'second', 'interval_seconds' => 30, 'last_run_at' => now()->subHours(2)]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    expect(Queue::pushed(SendChatMessageJob::class)->pluck('content')->all())->toBe(['first']);
});

test('the first timer waits its interval after the stream goes live', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Cache::put('kick:livestream:started_at', now()->subSeconds(29)->toIso8601String());
    Timer::factory()->create(['interval_seconds' => 30, 'last_run_at' => now()->subHour()]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('live-only timers are left out of the rotation while offline', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', false);
    Timer::factory()->create(['message' => 'live only', 'interval_seconds' => 30, 'last_run_at' => now()->subHour()]);
    Timer::factory()->runsWhileOffline()->create(['message' => 'always', 'interval_seconds' => 60, 'last_run_at' => now()->subSeconds(60)]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    expect(Queue::pushed(SendChatMessageJob::class)->pluck('content')->all())->toBe(['always']);
});

test('the timer fires once enough viewers have chatted since the previous timer message', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['interval_seconds' => 30, 'last_run_at' => now()->subMinutes(2)]);
    Timer::factory()->create(['message' => 'second', 'interval_seconds' => 60, 'min_messages_between' => 3, 'last_run_at' => now()->subHour()]);
    ChatMessage::factory()->count(3)->create(['sent_at' => now()->subMinute()]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    expect(Queue::pushed(SendChatMessageJob::class)->pluck('content')->all())->toBe(['second']);
});

test('bot messages do not count toward the minimum viewer messages', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    $bot = KickConnection::factory()->bot()->create();
    Timer::factory()->create(['interval_seconds' => 30, 'last_run_at' => now()->subMinutes(2)]);
    Timer::factory()->create(['interval_seconds' => 60, 'min_messages_between' => 3, 'last_run_at' => now()->subHour()]);
    ChatMessage::factory()->count(2)->create(['sent_at' => now()->subMinute()]);
    ChatMessage::factory()->count(2)->create(['sender_kick_user_id' => $bot->kick_user_id, 'sent_at' => now()->subMinute()]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('messages sent before the previous timer message do not count toward the minimum', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['interval_seconds' => 30, 'last_run_at' => now()->subMinutes(2)]);
    Timer::factory()->create(['interval_seconds' => 60, 'min_messages_between' => 3, 'last_run_at' => now()->subHour()]);
    ChatMessage::factory()->count(2)->create(['sent_at' => now()->subMinute()]);
    ChatMessage::factory()->create(['sent_at' => now()->subMinutes(3)]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('the rotation follows the timer order rather than creation order', function () {
    $this->freezeSecond();
    Cache::put('kick:livestream:is_live', true);
    Timer::factory()->create(['message' => 'created first', 'position' => 2, 'interval_seconds' => 30, 'last_run_at' => now()->subHour()]);
    Timer::factory()->create(['message' => 'created second', 'position' => 1, 'interval_seconds' => 30, 'last_run_at' => now()->subSeconds(30)]);
    Timer::factory()->create(['message' => 'created third', 'position' => 3, 'interval_seconds' => 30, 'last_run_at' => now()->subHours(2)]);

    $this->artisan('kick:run-timers')->assertSuccessful();

    expect(Queue::pushed(SendChatMessageJob::class)->pluck('content')->all())->toBe(['created first']);
});
