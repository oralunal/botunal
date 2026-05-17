<?php

namespace App\Models;

use App\Values\KickUserBanStatus;
use Database\Factories\KickUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kick_user_id', 'username', 'identity', 'first_seen_at', 'last_seen_at',
])]
class KickUser extends Model
{
    /** @use HasFactory<KickUserFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kick_user_id' => 'integer',
            'identity' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_kick_user_id', 'kick_user_id');
    }

    /**
     * @return HasMany<KickBan, $this>
     */
    public function bans(): HasMany
    {
        return $this->hasMany(KickBan::class, 'target_kick_user_id', 'kick_user_id');
    }

    /**
     * @return HasMany<KickUserNameChange, $this>
     */
    public function nameChanges(): HasMany
    {
        return $this->hasMany(KickUserNameChange::class, 'kick_user_id', 'kick_user_id');
    }

    /**
     * Filter users whose current or any previous username matches the term.
     *
     * @param  Builder<KickUser>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('username', 'like', $like)
                ->orWhereExists(function ($sub) use ($like): void {
                    $sub->selectRaw('1')
                        ->from('kick_user_name_changes')
                        ->whereColumn('kick_user_name_changes.kick_user_id', 'kick_users.kick_user_id')
                        ->where(function ($q) use ($like): void {
                            $q->where('previous_username', 'like', $like)
                                ->orWhere('new_username', 'like', $like);
                        });
                });
        });
    }

    /**
     * Derive the user's current moderation status from their latest ban record.
     */
    public function banStatus(): KickUserBanStatus
    {
        if ($this->kick_user_id === null) {
            return new KickUserBanStatus(KickUserBanStatus::STATUS_CLEAN, null);
        }

        $latest = KickBan::query()
            ->where('target_kick_user_id', $this->kick_user_id)
            ->latest('occurred_at')
            ->first(['action', 'expires_at']);

        if ($latest === null || $latest->action === KickBan::ACTION_UNBAN) {
            return new KickUserBanStatus(KickUserBanStatus::STATUS_CLEAN, null);
        }

        if ($latest->action === KickBan::ACTION_BAN) {
            return new KickUserBanStatus(KickUserBanStatus::STATUS_BANNED, null);
        }

        if ($latest->expires_at !== null && $latest->expires_at->isFuture()) {
            return new KickUserBanStatus(KickUserBanStatus::STATUS_TIMED_OUT, $latest->expires_at);
        }

        return new KickUserBanStatus(KickUserBanStatus::STATUS_CLEAN, null);
    }
}
