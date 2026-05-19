<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPermission>
 */
class UserPermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ability' => 'wiki.view',
        ];
    }
}
