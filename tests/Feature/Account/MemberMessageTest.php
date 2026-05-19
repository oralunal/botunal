<?php

use App\Models\MemberMessage;
use App\Models\User;

/**
 * A profile-complete, non-member account (kick_user_id null) is unaffected by
 * the profile.complete middleware, so it is ideal for the success paths.
 */
function profileCompleteUser(): User
{
    return User::factory()->create(['first_name' => 'A', 'last_name' => 'B']);
}

test('guests are redirected to login from the member messages page', function () {
    $this->get(route('account.messages.index'))->assertRedirect(route('login'));
});

test('the member messages page is displayed for authenticated users', function () {
    $user = profileCompleteUser();

    $this->actingAs($user)
        ->get(route('account.messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/Messages')
            ->has('messages.data')
        );
});

test('a member can send a message', function () {
    $user = profileCompleteUser();

    $response = $this
        ->actingAs($user)
        ->post(route('account.messages.store'), [
            'body' => 'Merhaba yayıncı!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.messages.index'));

    $this->assertDatabaseHas('member_messages', [
        'user_id' => $user->id,
        'body' => 'Merhaba yayıncı!',
        'is_read' => false,
    ]);
});

test('a success toast is flashed after sending a message', function () {
    $user = profileCompleteUser();

    $this->actingAs($user)
        ->post(route('account.messages.store'), [
            'body' => 'Selam!',
        ])
        ->assertRedirect(route('account.messages.index'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Message sent.',
        ]);
});

test('sending a message requires a body', function () {
    $user = profileCompleteUser();

    $this->actingAs($user)
        ->from(route('account.messages.index'))
        ->post(route('account.messages.store'), [])
        ->assertSessionHasErrors('body');
});

test('a message body may not exceed 2000 characters', function () {
    $user = profileCompleteUser();

    $this->actingAs($user)
        ->from(route('account.messages.index'))
        ->post(route('account.messages.store'), [
            'body' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('body');
});

test('a member only sees their own messages', function () {
    $user = profileCompleteUser();
    $other = profileCompleteUser();

    $own = MemberMessage::factory()->create(['user_id' => $user->id]);
    MemberMessage::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('account.messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/Messages')
            ->where('messages.data', fn ($data) => collect($data)->pluck('id')->all() === [$own->id])
        );
});

test('the member messages list paginates at 20 per page', function () {
    $user = profileCompleteUser();

    MemberMessage::factory()->count(21)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('account.messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('account/Messages')
            ->has('messages.data', 20)
            ->where('messages.total', 21)
            ->where('messages.per_page', 20)
            ->where('messages.last_page', 2)
        );
});

test('a profile-incomplete kick member is redirected from the messages page', function () {
    $user = User::factory()->create([
        'kick_user_id' => 950,
        'first_name' => null,
    ]);

    $this->actingAs($user)
        ->get(route('account.messages.index'))
        ->assertRedirect(route('account.edit'));
});
