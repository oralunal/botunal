<?php

use App\Jobs\Kick\ProcessChatMessageEvent;
use App\Models\KickWebhookEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Build an RSA keypair, fake Kick's public-key endpoint, and return a
 * helper that posts a correctly-signed (or tampered) webhook.
 */
function signedWebhook(array $payload, string $eventType = 'chat.message.sent', bool $tamper = false): \Illuminate\Testing\TestResponse
{
    $keyPair = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($keyPair, $privatePem);
    $publicPem = openssl_pkey_get_details($keyPair)['key'];

    Cache::forget('kick:public_key');
    Http::fake([
        'api.kick.com/public/v1/public-key' => Http::response(['data' => ['public_key' => $publicPem]]),
    ]);

    $messageId = (string) Str::ulid();
    $timestamp = now()->toIso8601String();
    $rawBody = json_encode($payload);

    openssl_sign("{$messageId}.{$timestamp}.{$rawBody}", $signature, $privatePem, OPENSSL_ALGO_SHA256);

    if ($tamper) {
        $signature = 'tampered'.$signature;
    }

    return test()->call('POST', '/kick/webhook', [], [], [], [
        'HTTP_Kick-Event-Message-Id' => $messageId,
        'HTTP_Kick-Event-Message-Timestamp' => $timestamp,
        'HTTP_Kick-Event-Signature' => base64_encode($signature),
        'HTTP_Kick-Event-Type' => $eventType,
        'HTTP_Kick-Event-Version' => '1',
        'CONTENT_TYPE' => 'application/json',
    ], $rawBody);
}

test('a correctly signed webhook is accepted, stored and queued', function () {
    Queue::fake();

    $payload = ['message_id' => 'abc', 'sender' => ['user_id' => 1, 'username' => 'bob'], 'content' => 'hi'];

    $response = signedWebhook($payload);

    $response->assertOk();
    expect(KickWebhookEvent::count())->toBe(1);
    Queue::assertPushed(ProcessChatMessageEvent::class);
});

test('a tampered signature is rejected with 403 and stores nothing', function () {
    Queue::fake();

    $response = signedWebhook(['message_id' => 'x'], tamper: true);

    $response->assertForbidden();
    expect(KickWebhookEvent::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('missing signature headers are rejected', function () {
    $this->postJson('/kick/webhook', ['message_id' => 'x'])
        ->assertForbidden();
});

test('duplicate deliveries are idempotent', function () {
    Queue::fake();

    $payload = ['message_id' => 'dupe', 'sender' => ['user_id' => 2, 'username' => 'amy'], 'content' => 'yo'];

    $first = (string) Str::ulid();

    // Manually craft two requests with the SAME message id.
    $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($keyPair, $privatePem);
    $publicPem = openssl_pkey_get_details($keyPair)['key'];
    Cache::forget('kick:public_key');
    Http::fake(['api.kick.com/public/v1/public-key' => Http::response(['data' => ['public_key' => $publicPem]])]);

    $send = function () use ($first, $payload, $privatePem) {
        $timestamp = now()->toIso8601String();
        $rawBody = json_encode($payload);
        openssl_sign("{$first}.{$timestamp}.{$rawBody}", $sig, $privatePem, OPENSSL_ALGO_SHA256);

        return $this->call('POST', '/kick/webhook', [], [], [], [
            'HTTP_Kick-Event-Message-Id' => $first,
            'HTTP_Kick-Event-Message-Timestamp' => $timestamp,
            'HTTP_Kick-Event-Signature' => base64_encode($sig),
            'HTTP_Kick-Event-Type' => 'chat.message.sent',
            'CONTENT_TYPE' => 'application/json',
        ], $rawBody);
    };

    $send()->assertOk();
    $send()->assertOk();

    expect(KickWebhookEvent::where('message_id', $first)->count())->toBe(1);
    Queue::assertPushed(ProcessChatMessageEvent::class, 1);
});
