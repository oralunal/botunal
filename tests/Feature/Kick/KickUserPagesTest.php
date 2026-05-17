<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Models\KickUser;
use App\Models\KickUserNameChange;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolve a deferred prop the way an Inertia partial reload would. The asset
 * version must match, so resolve it the same way the middleware does.
 */
function loadDeferred(object $test, string $url, array $only)
{
    $version = app(HandleInertiaRequests::class)->version(Request::create($url));

    return $test->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'kick/UserShow',
        'X-Inertia-Partial-Data' => implode(',', $only),
    ]);
}

test('user pages require authentication', function () {
    $user = KickUser::factory()->create();

    $this->get(route('kick.users.index'))->assertRedirect(route('login'));
    $this->get(route('kick.users.show', $user))->assertRedirect(route('login'));
});

test('the list paginates and is searchable by current and former username', function () {
    KickUser::factory()->count(60)->create();
    KickUser::factory()->create(['kick_user_id' => 5000, 'username' => 'findme']);
    $renamed = KickUser::factory()->create(['kick_user_id' => 6000, 'username' => 'newname']);
    KickUserNameChange::create([
        'kick_user_id' => 6000,
        'previous_username' => 'oldname',
        'new_username' => 'newname',
        'changed_at' => now(),
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('kick.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/Users')
            ->has('users.data', 50)
        );

    $this->get(route('kick.users.index', ['username' => 'findme']))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('filters.username', 'findme')
        );

    $this->get(route('kick.users.index', ['username' => 'oldname']))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $renamed->id)
        );
});

test('the detail page renders identity immediately and defers heavy sections', function () {
    $user = KickUser::factory()->create(['username' => 'someone']);
    KickUserNameChange::create([
        'kick_user_id' => $user->kick_user_id,
        'previous_username' => 'wasthis',
        'new_username' => 'someone',
        'changed_at' => now(),
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('kick.users.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kick/UserShow')
            ->where('user.username', 'someone')
            ->where('user.ban_status.status', 'clean')
            ->where('user.former_usernames', fn ($names) => collect($names)->contains('wasthis'))
            ->missing('messages')
            ->missing('events')
        );
});

test('ban status reflects the latest moderation record', function () {
    $user = KickUser::factory()->create(['kick_user_id' => 4242]);
    $this->actingAs(User::factory()->create());

    KickBan::factory()->create(['target_kick_user_id' => 4242, 'occurred_at' => now()->subMinutes(5)]);

    $this->get(route('kick.users.show', $user))
        ->assertInertia(fn ($page) => $page->where('user.ban_status.status', 'banned'));

    KickBan::factory()->timeout(30)->create([
        'target_kick_user_id' => 4242,
        'occurred_at' => now()->subMinutes(2),
    ]);

    $this->get(route('kick.users.show', $user))
        ->assertInertia(fn ($page) => $page
            ->where('user.ban_status.status', 'timed_out')
            ->where('user.ban_status.expires_at', fn ($v) => $v !== null)
        );

    KickBan::factory()->create([
        'target_kick_user_id' => 4242,
        'action' => KickBan::ACTION_UNBAN,
        'occurred_at' => now(),
    ]);

    $this->get(route('kick.users.show', $user))
        ->assertInertia(fn ($page) => $page->where('user.ban_status.status', 'clean'));
});

test('deleted messages are filterable and never hidden by default', function () {
    $user = KickUser::factory()->create(['kick_user_id' => 90]);
    ChatMessage::factory()->count(3)->create(['sender_kick_user_id' => 90]);
    ChatMessage::factory()->count(2)->create([
        'sender_kick_user_id' => 90,
        'deleted_at' => now(),
    ]);

    $this->actingAs(User::factory()->create());

    loadDeferred($this, route('kick.users.show', $user), ['messages'])
        ->assertOk()
        ->assertJsonCount(5, 'props.messages.data');

    loadDeferred($this, route('kick.users.show', [$user, 'deleted_only' => 1]), ['messages'])
        ->assertOk()
        ->assertJsonCount(2, 'props.messages.data');
});

test('the activity timeline is deferred and merged across sources', function () {
    $user = KickUser::factory()->create(['kick_user_id' => 70]);
    ChatMessage::factory()->create(['sender_kick_user_id' => 70]);
    KickBan::factory()->create(['target_kick_user_id' => 70]);

    $this->actingAs(User::factory()->create());

    loadDeferred($this, route('kick.users.show', $user), ['events'])
        ->assertOk()
        ->assertJsonCount(1, 'props.events.items')
        ->assertJsonPath('props.events.truncated', false);
});

test('the detail page 404s for an unknown user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('kick.users.show', 999999))->assertNotFound();
});
