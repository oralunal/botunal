<?php

test('super admin landing forwards to the dashboard', function () {
    $this->actingAs(asSuperAdmin())
        ->get(route('kick.landing'))
        ->assertRedirect(route('kick.dashboard'));
});

test('a member with only wiki.view lands on the wiki page', function () {
    $this->actingAs(asUserWith(['wiki.view']))
        ->get(route('kick.landing'))
        ->assertRedirect(route('kick.wiki.index'));
});

test('a member with only commands.manage lands on the commands page', function () {
    $this->actingAs(asUserWith(['commands.manage']))
        ->get(route('kick.landing'))
        ->assertRedirect(route('kick.commands.index'));
});

test('a member with no Kick permission is forbidden', function () {
    $this->actingAs(asUserWith([]))
        ->get(route('kick.landing'))
        ->assertForbidden();
});
