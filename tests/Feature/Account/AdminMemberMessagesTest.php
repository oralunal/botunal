<?php

use App\Models\MemberMessage;
use App\Models\User;

test('a user without member-messages.view cannot view the admin inbox', function () {
    $this->actingAs(asUserWith([]))
        ->get(route('kick.member-messages.index'))
        ->assertForbidden();
});

test('a user with member-messages.view sees the admin inbox with sender profile', function () {
    $member = User::factory()->create([
        'name' => 'Ali Veli',
        'first_name' => 'Ali',
        'last_name' => 'Veli',
        'email' => 'ali@example.com',
        'kick_username' => 'aliveli',
    ]);
    MemberMessage::factory()->create(['user_id' => $member->id]);

    $this->actingAs(asUserWith(['member-messages.view']))
        ->get(route('kick.member-messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/MemberMessages')
            ->has('messages.data', 1)
            ->where('messages.data.0.user.name', 'Ali Veli')
            ->where('messages.data.0.user.first_name', 'Ali')
            ->where('messages.data.0.user.last_name', 'Veli')
            ->where('messages.data.0.user.email', 'ali@example.com')
            ->where('messages.data.0.user.kick_username', 'aliveli')
        );
});

test('the super admin can view the admin inbox', function () {
    MemberMessage::factory()->create();

    $this->actingAs(asSuperAdmin())
        ->get(route('kick.member-messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/MemberMessages')
            ->has('messages.data', 1)
        );
});

test('a message can be marked as read', function () {
    $message = MemberMessage::factory()->unread()->create();

    $this->actingAs(asUserWith(['member-messages.view']))
        ->from(route('kick.member-messages.index'))
        ->patch(route('kick.member-messages.read', $message))
        ->assertRedirect(route('kick.member-messages.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Marked as read.',
        ]);

    $message->refresh();
    expect($message->is_read)->toBeTrue()
        ->and($message->read_at)->not->toBeNull();
});

test('a message can be marked as unread', function () {
    $message = MemberMessage::factory()->read()->create();

    $this->actingAs(asUserWith(['member-messages.view']))
        ->from(route('kick.member-messages.index'))
        ->patch(route('kick.member-messages.unread', $message))
        ->assertRedirect(route('kick.member-messages.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Marked as unread.',
        ]);

    $message->refresh();
    expect($message->is_read)->toBeFalse()
        ->and($message->read_at)->toBeNull();
});

test('the unread filter only returns unread messages', function () {
    $unread = MemberMessage::factory()->unread()->create();
    MemberMessage::factory()->read()->create();

    $this->actingAs(asUserWith(['member-messages.view']))
        ->get(route('kick.member-messages.index', ['unread' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/MemberMessages')
            ->has('messages.data', 1)
            ->where('messages.data.0.id', $unread->id)
            ->where('filters.unread', true)
        );
});

test('without the unread filter all messages are returned', function () {
    MemberMessage::factory()->unread()->create();
    MemberMessage::factory()->read()->create();

    $this->actingAs(asUserWith(['member-messages.view']))
        ->get(route('kick.member-messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/MemberMessages')
            ->has('messages.data', 2)
            ->where('filters.unread', false)
        );
});

test('marking a message read requires member-messages.view', function () {
    $message = MemberMessage::factory()->unread()->create();

    $this->actingAs(asUserWith([]))
        ->patch(route('kick.member-messages.read', $message))
        ->assertForbidden();

    expect($message->refresh()->is_read)->toBeFalse();
});
