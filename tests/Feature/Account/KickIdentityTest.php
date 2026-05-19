<?php

use App\Services\Kick\KickIdentity;
use Illuminate\Support\Facades\Http;

test('fetch returns the normalized identity when email is present', function () {
    Http::fake([
        config('services.kick.urls.api_base').'/users*' => Http::response([
            'data' => [['user_id' => 555, 'name' => 'TROLUNAL', 'email' => 'foo@example.com']],
        ], 200),
    ]);

    $identity = (new KickIdentity)->fetch('tok');

    expect($identity)->toBe([
        'user_id' => 555,
        'name' => 'TROLUNAL',
        'email' => 'foo@example.com',
    ]);
});

test('fetch returns a null email when Kick omits it', function () {
    Http::fake([
        config('services.kick.urls.api_base').'/users*' => Http::response([
            'data' => [['user_id' => 555, 'name' => 'TROLUNAL']],
        ], 200),
    ]);

    $identity = (new KickIdentity)->fetch('tok');

    expect($identity)->toBe([
        'user_id' => 555,
        'name' => 'TROLUNAL',
        'email' => null,
    ]);
});

test('fetch returns an all-null array when no user is returned', function () {
    Http::fake([
        config('services.kick.urls.api_base').'/users*' => Http::response(['data' => []], 200),
    ]);

    $identity = (new KickIdentity)->fetch('tok');

    expect($identity)->toBe([
        'user_id' => null,
        'name' => null,
        'email' => null,
    ]);
});

test('fetch throws when the Kick request fails', function () {
    Http::fake([
        config('services.kick.urls.api_base').'/users*' => Http::response([], 500),
    ]);

    expect(fn () => (new KickIdentity)->fetch('tok'))->toThrow(RuntimeException::class);
});

test('fetch authenticates the request with the bearer token', function () {
    Http::fake([
        config('services.kick.urls.api_base').'/users*' => Http::response([
            'data' => [['user_id' => 555, 'name' => 'TROLUNAL']],
        ], 200),
    ]);

    (new KickIdentity)->fetch('tok');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok'));
});
