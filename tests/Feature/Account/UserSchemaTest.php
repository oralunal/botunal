<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

test('the users table has the kick identity and profile columns', function () {
    expect(Schema::hasColumns('users', [
        'kick_user_id',
        'kick_username',
        'first_name',
        'last_name',
        'phone',
        'instagram',
        'twitter',
    ]))->toBeTrue();
});

test('a user can be persisted with a null password', function () {
    $user = User::factory()->create(['password' => null]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'password' => null,
    ]);
});

test('the kick_user_id column enforces a unique constraint', function () {
    User::factory()->create(['kick_user_id' => 999111]);

    expect(fn () => User::factory()->create(['kick_user_id' => 999111]))
        ->toThrow(UniqueConstraintViolationException::class);
});
