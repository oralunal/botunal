<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create and persist the super administrator (id === 1) with a complete
 * profile. If a user with id 1 already exists it is returned as-is.
 */
function asSuperAdmin(): User
{
    $existing = User::find(1);

    if ($existing !== null) {
        $existing->forceFill([
            'first_name' => filled($existing->first_name) ? $existing->first_name : 'Super',
            'last_name' => filled($existing->last_name) ? $existing->last_name : 'Admin',
            'email' => filled($existing->email) ? $existing->email : 'super-admin@example.com',
        ])->save();

        return $existing;
    }

    return User::factory()->create([
        'first_name' => 'Super',
        'last_name' => 'Admin',
    ]);
}

/**
 * Create and persist a non super administrator user (guaranteed id !== 1)
 * granted exactly the given abilities.
 *
 * @param  array<int, string>  $abilities
 */
function asUserWith(array $abilities): User
{
    if (User::find(1) === null) {
        User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
        ]);
    }

    $user = User::factory()->create([
        'first_name' => 'Member',
        'last_name' => 'User',
    ]);

    $user->syncPermissions($abilities);

    return $user;
}
