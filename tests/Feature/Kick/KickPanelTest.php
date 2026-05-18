<?php

use App\Models\ChatMessage;
use App\Models\KickConnection;
use App\Models\KickFollow;
use App\Models\User;

test('kick panel pages require authentication', function () {
    $this->get(route('kick.connections'))->assertRedirect(route('login'));
    $this->get(route('kick.dashboard'))->assertRedirect(route('login'));
    $this->get(route('kick.messages'))->assertRedirect(route('login'));
});

test('connections page exposes scope status without leaking tokens', function () {
    KickConnection::factory()->channel()->create(['slug' => 'trolunal']);
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('kick.connections'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/Connections')
            ->where('channel.connected', true)
            ->where('channel.slug', 'trolunal')
            ->where('bot.connected', false)
            ->missing('channel.access_token')
        );
});

test('messages page paginates and filters by username', function () {
    ChatMessage::factory()->count(60)->create();
    ChatMessage::factory()->create(['sender_username' => 'targetuser', 'content' => 'findme']);
    $this->actingAs(User::factory()->create());

    $this->get(route('kick.messages'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/Messages')
            ->has('messages.data', 50)
        );

    $this->get(route('kick.messages', ['username' => 'targetuser']))
        ->assertInertia(fn ($page) => $page->has('messages.data', 1));
});

test('the messages page exposes deleted_at so deleted rows can be flagged', function () {
    ChatMessage::factory()->create(['sender_username' => 'gone', 'deleted_at' => now()]);
    $this->actingAs(User::factory()->create());

    $this->get(route('kick.messages', ['username' => 'gone']))
        ->assertInertia(fn ($page) => $page
            ->component('kick/Messages')
            ->where('messages.data.0.deleted_at', fn ($v) => $v !== null)
        );
});

test('events page switches projection by type', function () {
    KickFollow::factory()->count(3)->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('kick.events', ['type' => 'follows']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/Events')
            ->where('type', 'follows')
            ->has('events.data', 3)
        );
});
