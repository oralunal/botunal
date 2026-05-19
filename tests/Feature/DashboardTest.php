<?php

use App\Models\ChatMessage;
use App\Models\KickFollow;
use App\Models\KickGift;
use App\Models\KickSubscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('password admin sees a minimal dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isKickMember', false)
            ->missing('recent_messages'),
        );
});

test('Kick member sees their own follow, messages, subscriptions and gifts', function () {
    $user = User::factory()->kickMember()->create([
        'kick_user_id' => 4242,
        'email_verified_at' => now(),
    ]);

    KickFollow::factory()->create([
        'follower_kick_user_id' => 4242,
        'followed_at' => now()->subDays(30),
    ]);

    // 12 messages — only the latest 10 should come back.
    foreach (range(1, 12) as $i) {
        ChatMessage::factory()->create([
            'sender_kick_user_id' => 4242,
            'content' => "mesaj-{$i}",
            'sent_at' => now()->subMinutes($i),
        ]);
    }

    KickSubscription::factory()->create([
        'subscriber_kick_user_id' => 4242,
        'type' => 'gift',
        'tier' => '1',
        'gifter_username' => 'biri',
        'occurred_at' => now()->subDays(1),
    ]);

    KickGift::factory()->create([
        'sender_kick_user_id' => 4242,
        'kicks_amount' => 250,
        'gift_name' => 'flowers',
        'occurred_at' => now()->subHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isKickMember', true)
            ->where('kick_username', $user->kick_username)
            ->has('follow.followed_at')
            ->has('recent_messages', 10)
            ->where('recent_messages.0.content', 'mesaj-1')
            ->has('recent_subscriptions', 1)
            ->where('recent_subscriptions.0.gifter_username', 'biri')
            ->has('recent_gifts', 1)
            ->where('recent_gifts.0.kicks_amount', 250),
        );
});

test('Kick member never sees another members data', function () {
    $me = User::factory()->kickMember()->create([
        'kick_user_id' => 100,
        'email_verified_at' => now(),
    ]);

    ChatMessage::factory()->create([
        'sender_kick_user_id' => 999,
        'content' => 'gizli',
        'sent_at' => now(),
    ]);
    KickGift::factory()->create([
        'sender_kick_user_id' => 999,
        'kicks_amount' => 500,
        'occurred_at' => now(),
    ]);

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent_messages', [])
            ->where('recent_gifts', []),
        );
});
