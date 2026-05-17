<?php

use App\Jobs\Kick\ProcessChatMessageEvent;
use App\Jobs\Kick\ProcessFollowEvent;
use App\Jobs\Kick\ProcessKicksGiftedEvent;
use App\Jobs\Kick\ProcessLivestreamEvent;
use App\Jobs\Kick\ProcessModerationBannedEvent;
use App\Jobs\Kick\ProcessRewardRedemptionEvent;
use App\Jobs\Kick\ProcessSubscriptionEvent;
use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Models\KickFollow;
use App\Models\KickGift;
use App\Models\KickSubscription;
use App\Models\KickWebhookEvent;
use App\Models\LivestreamEvent;
use App\Models\RewardRedemption;
use Illuminate\Support\Facades\Cache;

test('chat message event projects to chat_messages and marks processed', function () {
    $event = KickWebhookEvent::factory()->ofType('chat.message.sent', [
        'message_id' => 'msg-1',
        'sender' => ['user_id' => 7, 'username' => 'viewer'],
        'content' => '!discord please',
        'created_at' => now()->toIso8601String(),
    ])->create();

    (new ProcessChatMessageEvent($event->id))->handle();

    $message = ChatMessage::sole();
    expect($message->kick_message_id)->toBe('msg-1')
        ->and($message->sender_username)->toBe('viewer')
        ->and($message->is_command)->toBeTrue()
        ->and($event->fresh()->processed_at)->not->toBeNull();
});

test('event jobs are idempotent on retry', function () {
    $event = KickWebhookEvent::factory()->ofType('chat.message.sent', [
        'message_id' => 'msg-dupe',
        'sender' => ['user_id' => 7, 'username' => 'viewer'],
        'content' => 'hello',
    ])->create();

    (new ProcessChatMessageEvent($event->id))->handle();
    (new ProcessChatMessageEvent($event->id))->handle();

    expect(ChatMessage::count())->toBe(1);
});

test('follow event projects to kick_follows', function () {
    $event = KickWebhookEvent::factory()->ofType('channel.followed', [
        'follower' => ['user_id' => 3, 'username' => 'newfan'],
    ])->create();

    (new ProcessFollowEvent($event->id))->handle();

    expect(KickFollow::sole()->follower_username)->toBe('newfan');
});

test('subscription gift event records quantity and gifter', function () {
    $event = KickWebhookEvent::factory()->ofType('channel.subscription.gifts', [
        'gifter' => ['username' => 'santa'],
        'giftees' => [['username' => 'a'], ['username' => 'b'], ['username' => 'c']],
    ])->create();

    (new ProcessSubscriptionEvent($event->id))->handle();

    $sub = KickSubscription::sole();
    expect($sub->type)->toBe(KickSubscription::TYPE_GIFT)
        ->and($sub->gifter_username)->toBe('santa')
        ->and($sub->quantity)->toBe(3);
});

test('kicks gifted event records the amount', function () {
    $event = KickWebhookEvent::factory()->ofType('kicks.gifted', [
        'sender' => ['user_id' => 9, 'username' => 'whale'],
        'gift' => ['amount' => 500, 'name' => 'Rocket', 'message' => 'gg'],
    ])->create();

    (new ProcessKicksGiftedEvent($event->id))->handle();

    expect(KickGift::sole())
        ->sender_username->toBe('whale')
        ->kicks_amount->toBe(500);
});

test('reward redemption event projects and is keyed by redemption id', function () {
    $event = KickWebhookEvent::factory()->ofType('channel.reward.redemption.updated', [
        'id' => 'red-1',
        'status' => 'pending',
        'user_input' => 'song request',
        'reward' => ['title' => 'Song', 'cost' => 100],
        'redeemer' => ['user_id' => 4, 'username' => 'dj'],
    ])->create();

    (new ProcessRewardRedemptionEvent($event->id))->handle();

    expect(RewardRedemption::sole())
        ->kick_redemption_id->toBe('red-1')
        ->reward_title->toBe('Song')
        ->redeemer_username->toBe('dj');
});

test('moderation banned event distinguishes timeout from permanent ban', function () {
    $permanent = KickWebhookEvent::factory()->ofType('moderation.banned', [
        'banned_user' => ['user_id' => 1, 'username' => 'troll'],
        'moderator' => ['username' => 'mod'],
        'metadata' => ['reason' => 'spam', 'expires_at' => null],
    ])->create();

    (new ProcessModerationBannedEvent($permanent->id))->handle();

    expect(KickBan::sole()->action)->toBe(KickBan::ACTION_BAN);
});

test('livestream status event caches the live flag', function () {
    $event = KickWebhookEvent::factory()->ofType('livestream.status.updated', [
        'is_live' => true,
        'title' => 'Live now',
        'started_at' => now()->toIso8601String(),
    ])->create();

    (new ProcessLivestreamEvent($event->id))->handle();

    expect(LivestreamEvent::sole()->is_live)->toBeTrue()
        ->and(Cache::get('kick:livestream:is_live'))->toBeTrue();
});
