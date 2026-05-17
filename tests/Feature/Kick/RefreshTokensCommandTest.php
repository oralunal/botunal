<?php

use App\Models\KickConnection;
use Illuminate\Support\Facades\Http;

test('the command refreshes tokens that are near expiry', function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');

    $connection = KickConnection::factory()->channel()->create([
        'expires_at' => now()->addMinutes(2),
        'refresh_token' => 'old-refresh',
    ]);

    Http::fake([
        'id.kick.com/oauth/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'user:read',
        ]),
    ]);

    $this->artisan('kick:refresh-tokens')->assertSuccessful();

    $connection->refresh();
    expect($connection->access_token)->toBe('fresh-access')
        ->and($connection->refresh_token)->toBe('fresh-refresh')
        ->and($connection->expires_at->isFuture())->toBeTrue();
});

test('the command leaves healthy tokens untouched', function () {
    KickConnection::factory()->channel()->create([
        'expires_at' => now()->addHours(5),
        'access_token' => 'still-good',
    ]);

    Http::fake();

    $this->artisan('kick:refresh-tokens')->assertSuccessful();

    Http::assertNothingSent();
    expect(KickConnection::channel()->access_token)->toBe('still-good');
});
