<?php

use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Models\KickConnection;
use App\Models\KickUser;
use App\Services\Kick\KickApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 555]);
    $admin = asSuperAdmin();
    $admin->forceFill(['name' => 'AdminMod'])->save();
    $this->actingAs($admin);
});

test('a 400 on the bans endpoint maps to a privileged-account message', function () {
    $e = new KickApiException('post', '/moderation/bans', 400, '{"message":"Invalid request"}');

    expect($e->userMessage())
        ->toContain('moderatör')
        ->toContain('Yayıncı');
});

test('other Kick failures keep an informative generic message', function () {
    $e = new KickApiException('get', '/users', 500, 'upstream boom');

    expect($e->userMessage())
        ->toContain('500')
        ->toContain('upstream boom');
});

test('banning a privileged account surfaces the friendly error, not raw JSON', function () {
    ChatMessage::factory()->create(['sender_username' => 'modguy', 'sender_kick_user_id' => 321]);

    Http::fake([
        'api.kick.com/public/v1/moderation/bans' => Http::response(
            ['data' => [], 'message' => 'Invalid request'],
            400,
        ),
    ]);

    $this->post(route('kick.moderation.ban'), ['target' => 'modguy'])
        ->assertRedirect();

    expect(KickBan::count())->toBe(0);

    $toast = session('inertia.flash_data')['toast'] ?? null;
    expect($toast['type'])->toBe('error')
        ->and($toast['message'])->toContain('moderatör')
        ->and($toast['message'])->not->toContain('[400]');
});

test('unban from the user page surfaces the friendly error on a 400', function () {
    $user = KickUser::factory()->create(['kick_user_id' => 321, 'username' => 'modguy']);

    Http::fake([
        'api.kick.com/public/v1/moderation/bans' => Http::response(
            ['data' => [], 'message' => 'Invalid request'],
            400,
        ),
    ]);

    $this->delete(route('kick.users.unban', $user))->assertRedirect();

    expect(KickBan::count())->toBe(0);

    $toast = session('inertia.flash_data')['toast'] ?? null;
    expect($toast['message'])->toContain('moderatör');
});
