<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'kick_user_id', 'kick_username', 'first_name', 'last_name', 'phone', 'instagram', 'twitter'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'kick_user_id' => 'integer',
        ];
    }

    /**
     * Determine whether the user is the super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->id === 1;
    }

    /**
     * Determine whether the user has completed their profile.
     */
    public function isProfileComplete(): bool
    {
        return filled($this->first_name)
            && filled($this->last_name)
            && filled($this->email);
    }

    /**
     * @return HasMany<UserPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * @return HasMany<MemberMessage, $this>
     */
    public function memberMessages(): HasMany
    {
        return $this->hasMany(MemberMessage::class);
    }

    /**
     * Determine whether the user has the given ability.
     */
    public function hasPermission(string $ability): bool
    {
        return $this->isSuperAdmin()
            || $this->permissions()->where('ability', $ability)->exists();
    }

    /**
     * Grant the given ability to the user.
     */
    public function grantPermission(string $ability): void
    {
        $this->permissions()->firstOrCreate(['ability' => $ability]);
    }

    /**
     * Revoke the given ability from the user.
     */
    public function revokePermission(string $ability): void
    {
        $this->permissions()->where('ability', $ability)->delete();
    }

    /**
     * Synchronize the user's permissions with the given abilities.
     *
     * @param  array<int, string>  $abilities
     */
    public function syncPermissions(array $abilities): void
    {
        DB::transaction(function () use ($abilities): void {
            $this->permissions()->whereNotIn('ability', $abilities)->delete();

            foreach ($abilities as $ability) {
                $this->permissions()->firstOrCreate(['ability' => $ability]);
            }
        });
    }
}
