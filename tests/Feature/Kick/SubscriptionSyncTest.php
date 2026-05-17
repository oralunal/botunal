<?php

use App\Models\KickConnection;
use App\Models\KickEventSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'cid');
    config()->set('services.kick.client_secret', 'secret');
    KickConnection::factory()->channel()->create(['broadcaster_user_id' => 555]);
    $this->actingAs(User::factory()->create());
});

test('sync keys on kick_subscription_id and tolerates duplicate event tuples', function () {
    // Two subscriptions for the SAME event (same name/version/broadcaster) but
    // distinct ids — this is exactly what triggered the 1062 unique violation.
    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response([
            'data' => [
                ['id' => 'sub-A', 'name' => 'chat.message.sent', 'version' => 1, 'broadcaster_user_id' => 555],
                ['id' => 'sub-B', 'name' => 'chat.message.sent', 'version' => 1, 'broadcaster_user_id' => 555],
            ],
        ]),
    ]);

    $this->post(route('kick.subscriptions.sync'))
        ->assertRedirect(route('kick.subscriptions'));

    expect(KickEventSubscription::count())->toBe(2)
        ->and(KickEventSubscription::pluck('kick_subscription_id')->sort()->values()->all())
        ->toBe(['sub-A', 'sub-B']);

    // Idempotent: running again must not throw or duplicate.
    $this->post(route('kick.subscriptions.sync'))->assertRedirect();
    expect(KickEventSubscription::count())->toBe(2);
});

test('sync marks locally-known subscriptions missing from Kick as deleted', function () {
    KickEventSubscription::factory()->create([
        'kick_subscription_id' => 'stale-1',
        'status' => KickEventSubscription::STATUS_ACTIVE,
    ]);

    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response([
            'data' => [['id' => 'sub-A', 'name' => 'channel.followed', 'version' => 1]],
        ]),
    ]);

    $this->post(route('kick.subscriptions.sync'))->assertRedirect();

    expect(KickEventSubscription::where('kick_subscription_id', 'sub-A')->value('status'))
        ->toBe(KickEventSubscription::STATUS_ACTIVE)
        ->and(KickEventSubscription::where('kick_subscription_id', 'stale-1')->value('status'))
        ->toBe(KickEventSubscription::STATUS_DELETED);
});

test('store subscribes keyed by id and is idempotent', function () {
    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response([
            'data' => [
                ['id' => 'sub-A', 'name' => 'chat.message.sent', 'version' => 1],
                ['id' => 'sub-B', 'name' => 'channel.followed', 'version' => 1],
            ],
        ]),
    ]);

    $this->post(route('kick.subscriptions.store'))->assertRedirect(route('kick.subscriptions'));
    $this->post(route('kick.subscriptions.store'))->assertRedirect();

    expect(KickEventSubscription::count())->toBe(2);
});

test('entries without an id are skipped (never violate the unique key)', function () {
    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response([
            'data' => [
                ['name' => 'chat.message.sent', 'version' => 1],
                ['name' => 'channel.followed', 'version' => 1],
            ],
        ]),
    ]);

    $this->post(route('kick.subscriptions.sync'))->assertRedirect();

    expect(KickEventSubscription::count())->toBe(0);
});

test('a failing Kick API surfaces the error and redirects without a 500', function () {
    Exceptions::fake();

    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $this->post(route('kick.subscriptions.sync'))
        ->assertRedirect(route('kick.subscriptions'));

    Exceptions::assertReported(RuntimeException::class);
    expect(KickEventSubscription::count())->toBe(0);
});

test('destroy deletes on Kick with repeated id= query (not id[0]=) and removes the local row', function () {
    $sub = KickEventSubscription::factory()->create(['kick_subscription_id' => 'sub-A']);

    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response(['data' => []], 200),
    ]);

    $this->delete(route('kick.subscriptions.destroy', $sub))
        ->assertRedirect(route('kick.subscriptions'));

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), 'id=sub-A')
            && ! str_contains(rawurldecode($request->url()), 'id[');
    });

    expect(KickEventSubscription::find($sub->id))->toBeNull();
});

test('destroy keeps the local row and surfaces the error when Kick rejects', function () {
    $sub = KickEventSubscription::factory()->create(['kick_subscription_id' => 'sub-A']);

    Http::fake([
        'api.kick.com/public/v1/events/subscriptions*' => Http::response(['message' => 'Invalid request'], 400),
    ]);

    $this->delete(route('kick.subscriptions.destroy', $sub))
        ->assertRedirect(route('kick.subscriptions'));

    expect(KickEventSubscription::find($sub->id))->not->toBeNull();
});

test('subscription routes require authentication', function () {
    auth()->logout();

    $this->post(route('kick.subscriptions.sync'))->assertRedirect(route('login'));
    $this->post(route('kick.subscriptions.store'))->assertRedirect(route('login'));
});
