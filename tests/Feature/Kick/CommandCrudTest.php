<?php

use App\Models\Command;
use App\Models\Timer;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('a static command can be created with aliases', function () {
    $this->post(route('kick.commands.store'), [
        'name' => 'discord',
        'type' => 'static',
        'response' => 'Join {channel} discord!',
        'permission' => 'everyone',
        'cooldown_seconds' => 5,
        'user_cooldown_seconds' => 0,
        'is_enabled' => true,
        'reply_in_thread' => false,
        'aliases' => ['dc', 'disc'],
    ])->assertRedirect(route('kick.commands.index'));

    $command = Command::sole();
    expect($command->name)->toBe('discord')
        ->and($command->aliases)->toHaveCount(2);
});

test('command name must be unique and well formed', function () {
    Command::factory()->create(['name' => 'taken']);

    $this->post(route('kick.commands.store'), [
        'name' => 'taken',
        'type' => 'static',
        'response' => 'x',
        'permission' => 'everyone',
        'cooldown_seconds' => 0,
        'user_cooldown_seconds' => 0,
    ])->assertSessionHasErrors('name');
});

test('a dynamic command requires a valid handler', function () {
    $this->post(route('kick.commands.store'), [
        'name' => 'uptime',
        'type' => 'dynamic',
        'handler' => 'not-a-real-handler',
        'permission' => 'everyone',
        'cooldown_seconds' => 0,
        'user_cooldown_seconds' => 0,
    ])->assertSessionHasErrors('handler');
});

test('a command can be updated and deleted', function () {
    $command = Command::factory()->create(['name' => 'old']);

    $this->put(route('kick.commands.update', $command), [
        'name' => 'new',
        'type' => 'static',
        'response' => 'updated',
        'permission' => 'moderator',
        'cooldown_seconds' => 10,
        'user_cooldown_seconds' => 0,
    ])->assertRedirect(route('kick.commands.index'));

    expect($command->fresh()->name)->toBe('new');

    $this->delete(route('kick.commands.destroy', $command))
        ->assertRedirect(route('kick.commands.index'));

    expect(Command::count())->toBe(0);
});

test('timers can be created and validated', function () {
    $this->post(route('kick.timers.store'), [
        'name' => 'promo',
        'message' => 'Follow {channel}',
        'interval_seconds' => 600,
        'min_messages_between' => 0,
        'only_when_live' => true,
        'is_enabled' => true,
    ])->assertRedirect(route('kick.timers.index'));

    expect(Timer::sole()->name)->toBe('promo');

    $this->post(route('kick.timers.store'), ['name' => ''])
        ->assertSessionHasErrors(['name', 'message', 'interval_seconds']);
});

test('command pages require authentication', function () {
    auth()->logout();

    $this->get(route('kick.commands.index'))->assertRedirect(route('login'));
    $this->get(route('kick.timers.index'))->assertRedirect(route('login'));
    $this->get(route('kick.command-logs.index'))->assertRedirect(route('login'));
});
