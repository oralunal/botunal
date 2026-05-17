<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\KickConnection;
use App\Services\Kick\KickApiClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');
});

test('the bot path posts with type=bot and no broadcaster id', function () {
    KickConnection::factory()->bot()->create();

    Http::fake([
        'api.kick.com/public/v1/chat' => Http::response(['data' => ['message_id' => 'm1', 'is_sent' => true]]),
    ]);

    (new SendChatMessageJob('hello world', sendAs: 'bot'))->handle(app(KickApiClient::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.kick.com/public/v1/chat'
            && $request['type'] === 'bot'
            && $request['content'] === 'hello world'
            && ! isset($request['broadcaster_user_id']);
    });
});

test('the channel path posts with type=user and broadcaster id', function () {
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 777]);

    Http::fake([
        'api.kick.com/public/v1/chat' => Http::response(['data' => ['message_id' => 'm2', 'is_sent' => true]]),
    ]);

    (new SendChatMessageJob('hi', sendAs: 'user'))->handle(app(KickApiClient::class));

    Http::assertSent(fn ($request) => $request['type'] === 'user' && $request['broadcaster_user_id'] === 777);
});
