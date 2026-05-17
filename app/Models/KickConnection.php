<?php

namespace App\Models;

use Database\Factories\KickConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type', 'kick_user_id', 'slug', 'display_name', 'broadcaster_user_id',
    'access_token', 'refresh_token', 'scopes', 'token_type',
    'expires_at', 'connected_at', 'last_refreshed_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class KickConnection extends Model
{
    /** @use HasFactory<KickConnectionFactory> */
    use HasFactory;

    public const TYPE_CHANNEL = 'channel';

    public const TYPE_BOT = 'bot';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }

    /**
     * The channel (TROLUNAL) connection used for events and moderation.
     */
    public static function channel(): ?self
    {
        return static::firstWhere('type', self::TYPE_CHANNEL);
    }

    /**
     * The bot (botunal) connection used to post chat messages.
     */
    public static function bot(): ?self
    {
        return static::firstWhere('type', self::TYPE_BOT);
    }

    /**
     * Determine whether the access token is expired (with a 60s safety skew).
     */
    public function isExpired(): bool
    {
        return $this->expires_at === null
            || $this->expires_at->subSeconds(60)->isPast();
    }

    /**
     * Determine whether the connection was granted the given scope.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /**
     * @param  Builder<KickConnection>  $query
     */
    public function scopeChannelConnection(Builder $query): void
    {
        $query->where('type', self::TYPE_CHANNEL);
    }

    /**
     * @param  Builder<KickConnection>  $query
     */
    public function scopeBotConnection(Builder $query): void
    {
        $query->where('type', self::TYPE_BOT);
    }
}
