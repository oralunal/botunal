<?php

test('a user with wiki.view can view the wiki but not the dashboard or messages', function () {
    $this->actingAs(asUserWith(['wiki.view']));

    $this->get(route('kick.wiki.index'))->assertOk();
    $this->get(route('kick.dashboard'))->assertForbidden();
    $this->get(route('kick.messages'))->assertForbidden();
});

test('a user with dashboard.view can view the dashboard but not the wiki', function () {
    $this->actingAs(asUserWith(['dashboard.view']));

    $this->get(route('kick.dashboard'))->assertOk();
    $this->get(route('kick.wiki.index'))->assertForbidden();
});

test('the super administrator bypasses every permission gate', function () {
    $this->actingAs(asSuperAdmin());

    $this->get(route('kick.dashboard'))->assertOk();
    $this->get(route('kick.wiki.index'))->assertOk();
});

test('an authenticated user with no permissions is forbidden', function () {
    $this->actingAs(asUserWith([]));

    $this->get(route('kick.dashboard'))->assertForbidden();
});

test('the OAuth callback route is publicly reachable without authentication', function () {
    $response = $this->get(route('kick.oauth.callback', ['state' => 'nope']));

    // It must NOT be gated behind auth (no redirect to login) and must not 403.
    expect($response->getStatusCode())->not->toBe(403);

    if ($response->isRedirect()) {
        expect($response->headers->get('Location'))->not->toContain(route('login'));
    }
});

test('the member kick redirect sends a guest to the Kick authorize URL', function () {
    $response = $this->get(route('auth.kick.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toContain('id.kick.com')
        ->and($location)->toContain('scope=user%3Aread');
});
