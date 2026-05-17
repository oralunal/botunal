<?php

use App\Models\KickBan;
use App\Models\KickConnection;
use App\Models\KickUser;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 555]);
    $this->actingAs(User::factory()->create(['name' => 'AdminMod']));
});

test('unban from the detail page calls Kick and audits the action', function () {
    $user = KickUser::factory()->create(['kick_user_id' => 4242, 'username' => 'reformed']);

    Http::fake(['api.kick.com/public/v1/moderation/bans' => Http::response(['data' => []])]);

    $this->delete(route('kick.users.unban', $user))->assertRedirect();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request['broadcaster_user_id'] === 555
        && $request['user_id'] === 4242);

    $ban = KickBan::sole();
    expect($ban->action)->toBe(KickBan::ACTION_UNBAN)
        ->and($ban->source)->toBe(KickBan::SOURCE_DASHBOARD)
        ->and($ban->target_username)->toBe('reformed')
        ->and($ban->moderator_username)->toBe('AdminMod');
});

test('unban is unavailable for a user with no kick id', function () {
    $user = KickUser::factory()->withoutKickId()->create(['username' => 'ghost']);

    Http::fake();

    $this->delete(route('kick.users.unban', $user))->assertRedirect();

    Http::assertNothingSent();
    expect(KickBan::count())->toBe(0);
});

test('a Kick API failure does not write a false audit', function () {
    $user = KickUser::factory()->create(['kick_user_id' => 7]);

    Http::fake(['api.kick.com/public/v1/moderation/bans' => Http::response(['error' => 'nope'], 403)]);

    $this->delete(route('kick.users.unban', $user))->assertRedirect();

    expect(KickBan::count())->toBe(0);
});

test('unban requires authentication', function () {
    $user = KickUser::factory()->create();
    auth()->logout();

    $this->delete(route('kick.users.unban', $user))->assertRedirect(route('login'));
});
