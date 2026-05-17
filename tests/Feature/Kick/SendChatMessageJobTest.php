<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\KickConnection;
use App\Services\Kick\KickApiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');

    Http::fake([
        'api.kick.com/public/v1/chat' => Http::response(['data' => ['message_id' => 'm1', 'is_sent' => true]]),
    ]);
});

test('every send authenticates with the bot connection (the chat:write holder)', function () {
    $bot = KickConnection::factory()->bot()->create();
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 777]);

    (new SendChatMessageJob('hi', sendAs: 'user'))->handle(app(KickApiClient::class));

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.$bot->access_token));
});

test('user mode posts type=user with the channel broadcaster id', function () {
    KickConnection::factory()->bot()->create();
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 777]);

    (new SendChatMessageJob('hi', sendAs: 'user'))->handle(app(KickApiClient::class));

    Http::assertSent(fn ($request) => $request['type'] === 'user'
        && $request['broadcaster_user_id'] === 777
        && $request['content'] === 'hi');
});

test('the default send mode comes from config (user)', function () {
    config()->set('services.kick.send_as', 'user');
    KickConnection::factory()->bot()->create();
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 555]);

    (new SendChatMessageJob('default mode'))->handle(app(KickApiClient::class));

    Http::assertSent(fn ($request) => $request['type'] === 'user' && $request['broadcaster_user_id'] === 555);
});

test('bot mode is still supported via config for properly-registered chatbots', function () {
    KickConnection::factory()->bot()->create();

    (new SendChatMessageJob('hello', sendAs: 'bot'))->handle(app(KickApiClient::class));

    Http::assertSent(fn ($request) => $request['type'] === 'bot' && ! isset($request['broadcaster_user_id']));
});
