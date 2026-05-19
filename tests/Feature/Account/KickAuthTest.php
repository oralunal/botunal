<?php

use App\Models\KickConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.kick.client_id', 'client-id');
    config()->set('services.kick.client_secret', 'client-secret');
});

/**
 * Seed a member-login OAuth state and hit the shared callback dispatcher.
 */
function memberCallback(string $state = 'teststate', string $code = 'abc')
{
    Cache::put("kick:oauth:{$state}", [
        'purpose' => 'member-login',
        'verifier' => 'v',
    ], now()->addMinutes(10));

    return test()->get(route('kick.oauth.callback', ['state' => $state, 'code' => $code]));
}

/**
 * @param  array<string, mixed>  $identity
 */
function fakeMemberOAuth(array $identity, int $tokenStatus = 200): void
{
    $tokenUrl = config('services.kick.urls.token');
    $usersUrl = rtrim((string) config('services.kick.urls.api_base'), '/').'/users';

    Http::fake([
        $tokenUrl => Http::response([
            'access_token' => 't',
            'refresh_token' => 'r',
            'expires_in' => 3600,
            'scope' => 'user:read',
            'token_type' => 'Bearer',
        ], $tokenStatus),
        $usersUrl.'*' => Http::response(['data' => [$identity]], 200),
    ]);
}

test('the member kick redirect sends a guest to Kick and caches a member-login state', function () {
    $response = $this->get(route('auth.kick.redirect'));

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');

    expect($location)->toContain((string) parse_url((string) config('services.kick.urls.authorize'), PHP_URL_HOST))
        ->and($location)->toContain('scope=user%3Aread');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $state = (string) ($query['state'] ?? '');

    $cached = Cache::get("kick:oauth:{$state}");

    expect($cached)->toBeArray()
        ->and($cached['purpose'])->toBe('member-login');
});

test('callback logs in a member with an email and redirects an incomplete profile to account', function () {
    fakeMemberOAuth(['user_id' => 777, 'name' => 'Trolly', 'email' => 't@e.com']);

    $response = memberCallback();

    $response->assertRedirect(route('account.edit'));

    $user = User::where('kick_user_id', 777)->first();

    expect($user)->not->toBeNull()
        ->and($user->kick_user_id)->toBe(777)
        ->and($user->email)->toBe('t@e.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->kick_username)->toBe('Trolly');

    $this->assertAuthenticatedAs($user);
    expect(KickConnection::count())->toBe(0);
});

test('callback logs in a member without an email', function () {
    fakeMemberOAuth(['user_id' => 778, 'name' => 'NoMail']);

    $response = memberCallback();

    $response->assertRedirect(route('account.edit'));

    $user = User::where('kick_user_id', 778)->first();

    // users.email is nullable + unique; with no Kick email it stays null and
    // unverified so the profile is incomplete and the member is sent to
    // account.edit to provide a real address.
    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->isProfileComplete())->toBeFalse();

    $this->assertAuthenticatedAs($user);
    expect(KickConnection::count())->toBe(0);
});

test('callback is idempotent for an existing member', function () {
    // Reserve id 1 so the member under test is not the super admin.
    asSuperAdmin();

    User::factory()->create([
        'kick_user_id' => 779,
        'first_name' => 'A',
        'last_name' => 'B',
        'email' => 'ab@e.com',
    ]);

    fakeMemberOAuth(['user_id' => 779, 'name' => 'Existing', 'email' => 'changed@e.com']);

    $response = memberCallback();

    expect(User::where('kick_user_id', 779)->count())->toBe(1);

    $user = User::where('kick_user_id', 779)->first();
    $this->assertAuthenticatedAs($user);

    // Profile complete but no permissions -> account.
    $response->assertRedirect(route('account.edit'));
});

test('a profile-complete member with a permission lands on the dashboard', function () {
    // Reserve id 1 so the dashboard redirect is driven by the permission,
    // not by the super-admin shortcut.
    asSuperAdmin();

    $member = User::factory()->create([
        'kick_user_id' => 780,
        'first_name' => 'C',
        'last_name' => 'D',
        'email' => 'cd@e.com',
    ]);
    $member->grantPermission('dashboard.view');

    fakeMemberOAuth(['user_id' => 780, 'name' => 'Privileged', 'email' => 'cd@e.com']);

    $response = memberCallback();

    $response->assertRedirect(route('kick.dashboard'));
    $this->assertAuthenticatedAs($member->fresh());
});

test('a member-login callback with no code aborts the login', function () {
    Cache::put('kick:oauth:nocode', [
        'purpose' => 'member-login',
        'verifier' => 'v',
    ], now()->addMinutes(10));

    $response = $this->get(route('kick.oauth.callback', ['state' => 'nocode']));

    $response->assertRedirect(route('register'));
    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('a failed token exchange aborts the member login without creating a user', function () {
    fakeMemberOAuth(['user_id' => 781, 'name' => 'Boom'], tokenStatus: 500);

    $response = memberCallback();

    $response->assertRedirect(route('register'));
    expect(User::where('kick_user_id', 781)->count())->toBe(0);
    $this->assertGuest();
});

test('an error param aborts the member login', function () {
    Cache::put('kick:oauth:errstate', [
        'purpose' => 'member-login',
        'verifier' => 'v',
    ], now()->addMinutes(10));

    $response = $this->get(route('kick.oauth.callback', [
        'state' => 'errstate',
        'error' => 'access_denied',
        'error_description' => 'No',
    ]));

    $response->assertRedirect(route('register'));
    $this->assertGuest();
});
