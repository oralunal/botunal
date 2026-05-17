<?php

use App\Models\KickConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'client-id');
    config()->set('services.kick.client_secret', 'client-secret');
    config()->set('services.kick.channel_slug', 'trolunal');
    $this->actingAs(User::factory()->create());
});

test('redirect stores PKCE state and sends the user to Kick', function () {
    $response = $this->get(route('kick.oauth.redirect', ['type' => 'channel']));

    $response->assertRedirectContains('https://id.kick.com/oauth/authorize');
    $response->assertRedirectContains('code_challenge_method=S256');
});

test('callback exchanges the code and stores the channel connection', function () {
    Cache::put('kick:oauth:state123', ['type' => 'channel', 'verifier' => 'verifier-abc'], now()->addMinutes(10));

    Http::fake([
        'id.kick.com/oauth/token' => Http::response([
            'access_token' => 'access-tok',
            'refresh_token' => 'refresh-tok',
            'expires_in' => 3600,
            'scope' => 'events:subscribe user:read',
            'token_type' => 'Bearer',
        ]),
        'api.kick.com/public/v1/users*' => Http::response(['data' => [['user_id' => 555, 'name' => 'TROLUNAL']]]),
        'api.kick.com/public/v1/channels*' => Http::response(['data' => [['broadcaster_user_id' => 999]]]),
    ]);

    $response = $this->get(route('kick.oauth.callback', ['code' => 'auth-code', 'state' => 'state123']));

    $response->assertRedirect(route('kick.connections'));

    $connection = KickConnection::channel();
    expect($connection)->not->toBeNull()
        ->and($connection->access_token)->toBe('access-tok')
        ->and($connection->refresh_token)->toBe('refresh-tok')
        ->and($connection->kick_user_id)->toBe(555)
        ->and($connection->broadcaster_user_id)->toBe(999)
        ->and($connection->scopes)->toContain('events:subscribe');

    // Token is encrypted at rest.
    $raw = DB::table('kick_connections')->where('type', 'channel')->value('access_token');
    expect($raw)->not->toBe('access-tok');
});

test('callback rejects an unknown or expired state', function () {
    $response = $this->get(route('kick.oauth.callback', ['code' => 'auth-code', 'state' => 'missing']));

    $response->assertRedirect(route('kick.connections'));
    expect(KickConnection::count())->toBe(0);
});

test('the PKCE state is single use', function () {
    Cache::put('kick:oauth:once', ['type' => 'bot', 'verifier' => 'v'], now()->addMinutes(10));

    Http::fake([
        'id.kick.com/oauth/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'token_type' => 'Bearer',
        ]),
        'api.kick.com/public/v1/users*' => Http::response(['data' => [['user_id' => 1, 'name' => 'botunal']]]),
    ]);

    $this->get(route('kick.oauth.callback', ['code' => 'c', 'state' => 'once']));
    expect(KickConnection::count())->toBe(1);

    // Second use of the same state must fail (state was pulled).
    $this->get(route('kick.oauth.callback', ['code' => 'c', 'state' => 'once']));
    expect(KickConnection::count())->toBe(1);
});

test('guests cannot start the OAuth flow', function () {
    auth()->logout();

    $this->get(route('kick.oauth.redirect', ['type' => 'channel']))
        ->assertRedirect(route('login'));
});
