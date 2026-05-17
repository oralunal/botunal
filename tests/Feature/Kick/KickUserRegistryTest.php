<?php

use App\Jobs\Kick\ProcessChatMessageEvent;
use App\Jobs\Kick\ProcessFollowEvent;
use App\Jobs\Kick\ProcessKicksGiftedEvent;
use App\Jobs\Kick\ProcessModerationBannedEvent;
use App\Jobs\Kick\ProcessRewardRedemptionEvent;
use App\Jobs\Kick\ProcessSubscriptionEvent;
use App\Models\ChatMessage;
use App\Models\KickUser;
use App\Models\KickUserNameChange;
use App\Models\KickWebhookEvent;
use Carbon\CarbonInterface;

function runChat(int $userId, string $username, CarbonInterface $at, string $messageId = 'm1'): void
{
    $event = KickWebhookEvent::factory()->ofType('chat.message.sent', [
        'message_id' => $messageId,
        'sender' => ['user_id' => $userId, 'username' => $username, 'identity' => ['badges' => ['mod']]],
        'content' => 'hello',
        'created_at' => $at->toIso8601String(),
    ])->create();

    (new ProcessChatMessageEvent($event->id))->handle();
}

test('each event job registers the user', function () {
    runChat(7, 'viewer', now());
    expect(KickUser::where('kick_user_id', 7)->where('username', 'viewer')->exists())->toBeTrue();

    $follow = KickWebhookEvent::factory()->ofType('channel.followed', [
        'follower' => ['user_id' => 3, 'username' => 'newfan'],
    ])->create();
    (new ProcessFollowEvent($follow->id))->handle();
    expect(KickUser::where('kick_user_id', 3)->where('username', 'newfan')->exists())->toBeTrue();

    $sub = KickWebhookEvent::factory()->ofType('channel.subscription.new', [
        'subscriber' => ['user_id' => 9, 'username' => 'subby'],
    ])->create();
    (new ProcessSubscriptionEvent($sub->id))->handle();
    expect(KickUser::where('kick_user_id', 9)->exists())->toBeTrue();

    $kicks = KickWebhookEvent::factory()->ofType('kicks.gifted', [
        'sender' => ['user_id' => 11, 'username' => 'whale'],
        'gift' => ['amount' => 100],
    ])->create();
    (new ProcessKicksGiftedEvent($kicks->id))->handle();
    expect(KickUser::where('kick_user_id', 11)->exists())->toBeTrue();

    $ban = KickWebhookEvent::factory()->ofType('moderation.banned', [
        'banned_user' => ['user_id' => 1, 'username' => 'troll'],
        'moderator' => ['username' => 'mod'],
        'metadata' => ['reason' => 'spam'],
    ])->create();
    (new ProcessModerationBannedEvent($ban->id))->handle();
    expect(KickUser::where('kick_user_id', 1)->exists())->toBeTrue();

    $reward = KickWebhookEvent::factory()->ofType('channel.reward.redemption.updated', [
        'id' => 'r1',
        'reward' => ['title' => 'Song', 'cost' => 100],
        'redeemer' => ['user_id' => 4, 'username' => 'dj'],
    ])->create();
    (new ProcessRewardRedemptionEvent($reward->id))->handle();
    expect(KickUser::where('kick_user_id', 4)->exists())->toBeTrue();
});

test('a username change is recorded and first_seen_at is preserved', function () {
    $first = now()->subDay();
    runChat(7, 'viewer', $first, 'm1');

    $original = KickUser::where('kick_user_id', 7)->sole();

    runChat(7, 'viewer_v2', now(), 'm2');

    $user = KickUser::where('kick_user_id', 7)->sole();

    expect($user->username)->toBe('viewer_v2')
        ->and($user->first_seen_at->equalTo($original->first_seen_at))->toBeTrue()
        ->and($user->last_seen_at->greaterThan($user->first_seen_at))->toBeTrue();

    $change = KickUserNameChange::where('kick_user_id', 7)->sole();
    expect($change->previous_username)->toBe('viewer')
        ->and($change->new_username)->toBe('viewer_v2');
});

test('the registry upsert is idempotent', function () {
    $event = KickWebhookEvent::factory()->ofType('chat.message.sent', [
        'message_id' => 'dupe',
        'sender' => ['user_id' => 7, 'username' => 'viewer'],
        'content' => 'hi',
    ])->create();

    (new ProcessChatMessageEvent($event->id))->handle();
    (new ProcessChatMessageEvent($event->id))->handle();

    runChat(7, 'viewer', now()->addMinute(), 'later');

    expect(KickUser::count())->toBe(1)
        ->and(KickUserNameChange::count())->toBe(0);
});

test('a gift subscription with no subscriber does not create a junk user', function () {
    $event = KickWebhookEvent::factory()->ofType('channel.subscription.gifts', [
        'gifter' => ['username' => 'santa'],
        'giftees' => [['username' => 'a'], ['username' => 'b']],
    ])->create();

    (new ProcessSubscriptionEvent($event->id))->handle();

    expect(KickUser::count())->toBe(0);
});

test('users without a kick id are keyed by username and do not collide', function () {
    foreach (['anon1', 'anon1', 'anon2'] as $name) {
        $event = KickWebhookEvent::factory()->ofType('channel.followed', [
            'follower' => ['username' => $name],
        ])->create();
        (new ProcessFollowEvent($event->id))->handle();
    }

    expect(KickUser::whereNull('kick_user_id')->count())->toBe(2);
});

test('the backfill command rebuilds the registry and rename history idempotently', function () {
    ChatMessage::factory()->create([
        'kick_message_id' => 'b1',
        'sender_kick_user_id' => 100,
        'sender_username' => 'alpha',
        'sent_at' => now()->subDays(2),
    ]);
    ChatMessage::factory()->create([
        'kick_message_id' => 'b2',
        'sender_kick_user_id' => 100,
        'sender_username' => 'beta',
        'sent_at' => now()->subDay(),
    ]);
    ChatMessage::factory()->create([
        'kick_message_id' => 'b3',
        'sender_kick_user_id' => 200,
        'sender_username' => 'gamma',
        'sent_at' => now(),
    ]);

    $this->artisan('kick:backfill-users')->assertSuccessful();
    $this->artisan('kick:backfill-users')->assertSuccessful();

    expect(KickUser::count())->toBe(2)
        ->and(KickUser::where('kick_user_id', 100)->value('username'))->toBe('beta')
        ->and(KickUserNameChange::count())->toBe(1);

    $change = KickUserNameChange::sole();
    expect($change->previous_username)->toBe('alpha')
        ->and($change->new_username)->toBe('beta');
});
