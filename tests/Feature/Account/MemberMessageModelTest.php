<?php

use App\Models\MemberMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

test('the migration creates the member_messages table with expected columns', function () {
    expect(Schema::hasTable('member_messages'))->toBeTrue();

    expect(Schema::hasColumns('member_messages', [
        'id', 'user_id', 'body', 'is_read', 'read_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a member message belongs to a user', function () {
    $message = MemberMessage::factory()->create();

    expect($message->user())->toBeInstanceOf(BelongsTo::class);
    expect($message->user)->toBeInstanceOf(User::class);
});

test('a user has many member messages scoped to that user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $first = MemberMessage::factory()->for($user)->create();
    $second = MemberMessage::factory()->for($user)->create();
    $foreign = MemberMessage::factory()->for($other)->create();

    expect($user->memberMessages()->count())->toBe(2);
    expect($user->memberMessages->pluck('id')->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
    expect($user->memberMessages->pluck('id')->all())
        ->not->toContain($foreign->id);
});

test('the is_read and read_at attributes are cast', function () {
    $read = MemberMessage::factory()->read()->create();

    expect($read->is_read)->toBeBool()->toBeTrue();
    expect($read->read_at)->toBeInstanceOf(CarbonInterface::class);

    $unread = MemberMessage::factory()->unread()->create();

    expect($unread->is_read)->toBeBool()->toBeFalse();
    expect($unread->read_at)->toBeNull();
});

test('the read factory state marks the message read', function () {
    $message = MemberMessage::factory()->read()->create();

    expect($message->is_read)->toBeTrue();
    expect($message->read_at)->not->toBeNull();
});

test('the unread factory state marks the message unread', function () {
    $message = MemberMessage::factory()->unread()->create();

    expect($message->is_read)->toBeFalse();
    expect($message->read_at)->toBeNull();
});

test('the default factory state is unread', function () {
    $message = MemberMessage::factory()->create();

    expect($message->is_read)->toBeFalse();
    expect($message->read_at)->toBeNull();
});

test('the four fields are mass assignable', function () {
    $user = User::factory()->create();

    $message = MemberMessage::create([
        'user_id' => $user->id,
        'body' => 'Hello there.',
        'is_read' => true,
        'read_at' => now(),
    ]);

    expect($message->user_id)->toBe($user->id);
    expect($message->body)->toBe('Hello there.');
    expect($message->is_read)->toBeTrue();
    expect($message->read_at)->toBeInstanceOf(CarbonInterface::class);

    $this->assertDatabaseHas('member_messages', [
        'id' => $message->id,
        'user_id' => $user->id,
        'body' => 'Hello there.',
        'is_read' => true,
    ]);
});
