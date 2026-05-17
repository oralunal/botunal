<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\Command;
use App\Models\CommandLog;
use App\Models\KickConnection;
use App\Services\Kick\CommandDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.kick.command_prefix', '!');
    config()->set('services.kick.channel_slug', 'trolunal');
    Queue::fake();
});

function dispatchMessage(string $content, array $identity = [], int $userId = 42): ChatMessage
{
    $message = ChatMessage::factory()->create([
        'content' => $content,
        'is_command' => true,
        'sender_kick_user_id' => $userId,
        'sender_identity' => ['badges' => $identity],
    ]);

    app(CommandDispatcher::class)->handle($message);

    return $message;
}

test('a static command renders placeholders and is queued', function () {
    Command::factory()->create([
        'name' => 'hello',
        'response' => 'Hi {user}, welcome to {channel}!',
    ]);

    dispatchMessage('!hello', userId: 1);

    Queue::assertPushed(SendChatMessageJob::class, function (SendChatMessageJob $job) {
        return $job->content === 'Hi '.ChatMessage::first()->sender_username.', welcome to trolunal!';
    });
    expect(CommandLog::where('outcome', 'sent')->count())->toBe(1);
});

test('commands resolve by alias', function () {
    $command = Command::factory()->create(['name' => 'discord', 'response' => 'join us']);
    $command->aliases()->create(['alias' => 'dc']);

    dispatchMessage('!dc');

    Queue::assertPushed(SendChatMessageJob::class);
});

test('disabled commands are ignored', function () {
    Command::factory()->disabled()->create(['name' => 'secret', 'response' => 'nope']);

    dispatchMessage('!secret');

    Queue::assertNothingPushed();
});

test('permission tiers are enforced', function () {
    Command::factory()->permission(Command::PERMISSION_MODERATOR)->create([
        'name' => 'modonly',
        'response' => 'ok',
    ]);

    dispatchMessage('!modonly', identity: []);
    Queue::assertNothingPushed();
    expect(CommandLog::where('outcome', 'denied')->count())->toBe(1);

    dispatchMessage('!modonly', identity: [['type' => 'moderator']]);
    Queue::assertPushed(SendChatMessageJob::class);
});

test('global cooldown blocks rapid reuse', function () {
    Command::factory()->create([
        'name' => 'spam',
        'response' => 'x',
        'cooldown_seconds' => 60,
    ]);

    dispatchMessage('!spam', userId: 1);
    dispatchMessage('!spam', userId: 2);

    Queue::assertPushed(SendChatMessageJob::class, 1);
    expect(CommandLog::where('outcome', 'cooldown')->count())->toBe(1);
});

test('dynamic built-in commands resolve', function () {
    Command::factory()->dynamic('uptime')->create(['name' => 'uptime']);
    Cache::put('kick:livestream:is_live', false);

    dispatchMessage('!uptime');

    Queue::assertPushed(SendChatMessageJob::class, function (SendChatMessageJob $job) {
        return str_contains($job->content, 'offline');
    });
});

test('the bot never triggers its own commands', function () {
    KickConnection::factory()->bot()->create(['kick_user_id' => 999]);
    Command::factory()->create(['name' => 'loop', 'response' => 'echo']);

    dispatchMessage('!loop', userId: 999);

    Queue::assertNothingPushed();
});

test('unknown commands do nothing', function () {
    dispatchMessage('!doesnotexist');

    Queue::assertNothingPushed();
    expect(CommandLog::count())->toBe(0);
});
