<?php

use App\Http\Requests\Account\AccountProfileUpdateRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * @return array<string, mixed>
 */
function validAccountProfilePayload(): array
{
    return [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
        'phone' => '+90 555 123 4567',
        'instagram' => 'janedoe',
        'twitter' => 'janedoe',
    ];
}

test('rules expose the expected account profile keys', function () {
    $user = asUserWith([]);

    $request = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user);

    expect(array_keys($request->rules()))
        ->toBe(['first_name', 'last_name', 'email', 'phone', 'instagram', 'twitter']);
});

test('authorize is true with a user and false without', function () {
    $request = new AccountProfileUpdateRequest;

    $request->setUserResolver(fn () => asUserWith([]));
    expect($request->authorize())->toBeTrue();

    $request->setUserResolver(fn () => null);
    expect($request->authorize())->toBeFalse();
});

test('a full valid payload passes', function () {
    $user = asUserWith([]);

    $rules = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user)->rules();

    expect(Validator::make(validAccountProfilePayload(), $rules)->passes())->toBeTrue();
});

test('a missing first_name fails', function () {
    $user = asUserWith([]);

    $rules = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user)->rules();

    $data = validAccountProfilePayload();
    unset($data['first_name']);

    expect(Validator::make($data, $rules)->fails())->toBeTrue();
});

test('an invalid email fails', function () {
    $user = asUserWith([]);

    $rules = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user)->rules();

    $data = validAccountProfilePayload();
    $data['email'] = 'not-an-email';

    expect(Validator::make($data, $rules)->fails())->toBeTrue();
});

test('an email belonging to another user fails', function () {
    $other = asUserWith([]);
    $other->forceFill(['email' => 'taken@example.com'])->save();

    $user = asUserWith([]);

    $rules = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user)->rules();

    $data = validAccountProfilePayload();
    $data['email'] = 'taken@example.com';

    expect(Validator::make($data, $rules)->fails())->toBeTrue();
});

test('the same user own email passes (ignore-self works)', function () {
    $user = asUserWith([]);
    $user->forceFill(['email' => 'mine@example.com'])->save();

    $rules = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user)->rules();

    $data = validAccountProfilePayload();
    $data['email'] = 'mine@example.com';

    expect(Validator::make($data, $rules)->passes())->toBeTrue();
});

test('phone, instagram and twitter are optional', function () {
    $user = asUserWith([]);

    $rules = (new AccountProfileUpdateRequest)->setUserResolver(fn (): User => $user)->rules();

    $data = validAccountProfilePayload();
    unset($data['phone'], $data['instagram'], $data['twitter']);

    expect(Validator::make($data, $rules)->passes())->toBeTrue();
});
