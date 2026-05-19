<?php

use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Models\KickConnection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 555]);
    $admin = asSuperAdmin();
    $admin->forceFill(['name' => 'AdminMod'])->save();
    $this->actingAs($admin);
});

test('a permanent ban is sent to Kick and audited', function () {
    ChatMessage::factory()->create(['sender_username' => 'troll', 'sender_kick_user_id' => 4242]);

    Http::fake([
        'api.kick.com/public/v1/moderation/bans' => Http::response(['data' => []]),
    ]);

    $this->post(route('kick.moderation.ban'), ['target' => 'troll'])
        ->assertRedirect();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['broadcaster_user_id'] === 555
        && $request['user_id'] === 4242
        && ! isset($request['duration']));

    $ban = KickBan::sole();
    expect($ban->action)->toBe(KickBan::ACTION_BAN)
        ->and($ban->source)->toBe(KickBan::SOURCE_DASHBOARD)
        ->and($ban->moderator_username)->toBe('AdminMod');
});

test('a timeout includes a duration', function () {
    ChatMessage::factory()->create(['sender_username' => 'spammer', 'sender_kick_user_id' => 11]);

    Http::fake(['api.kick.com/public/v1/moderation/bans' => Http::response(['data' => []])]);

    $this->post(route('kick.moderation.ban'), ['target' => 'spammer', 'duration_minutes' => 15]);

    Http::assertSent(fn ($request) => $request['duration'] === 15);
    expect(KickBan::sole()->action)->toBe(KickBan::ACTION_TIMEOUT);
});

test('a numeric id is used directly when the user never chatted', function () {
    Http::fake(['api.kick.com/public/v1/moderation/bans' => Http::response(['data' => []])]);

    $this->post(route('kick.moderation.ban'), ['target' => '98765']);

    Http::assertSent(fn ($request) => $request['user_id'] === 98765);
});

test('an unresolvable username produces an error and no API call', function () {
    Http::fake();

    $this->post(route('kick.moderation.ban'), ['target' => 'ghostuser'])
        ->assertRedirect();

    Http::assertNothingSent();
    expect(KickBan::count())->toBe(0);
});

test('a Kick API failure surfaces an error and does not write a false audit', function () {
    ChatMessage::factory()->create(['sender_username' => 'x', 'sender_kick_user_id' => 7]);

    Http::fake(['api.kick.com/public/v1/moderation/bans' => Http::response(['error' => 'nope'], 403)]);

    $this->post(route('kick.moderation.ban'), ['target' => 'x'])->assertRedirect();

    expect(KickBan::count())->toBe(0);
});

test('unban removes the ban and audits it', function () {
    ChatMessage::factory()->create(['sender_username' => 'reformed', 'sender_kick_user_id' => 3]);

    Http::fake(['api.kick.com/public/v1/moderation/bans' => Http::response(['data' => []])]);

    $this->delete(route('kick.moderation.unban'), ['target' => 'reformed'])
        ->assertRedirect();

    expect(KickBan::sole()->action)->toBe(KickBan::ACTION_UNBAN);
});

test('deleting a message calls Kick and marks it deleted locally', function () {
    $message = ChatMessage::factory()->create(['kick_message_id' => 'uuid-1']);

    Http::fake(['api.kick.com/public/v1/chat/uuid-1' => Http::response('', 204)]);

    $this->delete(route('kick.moderation.message'), ['message_id' => 'uuid-1'])
        ->assertRedirect();

    expect($message->fresh()->deleted_at)->not->toBeNull();
});

test('moderation pages and actions require authentication', function () {
    auth()->logout();

    $this->get(route('kick.moderation.index'))->assertRedirect(route('login'));
    $this->post(route('kick.moderation.ban'), ['target' => 'x'])->assertRedirect(route('login'));
});
