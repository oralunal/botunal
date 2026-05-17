<?php

namespace App\Models;

use Database\Factories\CommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'type', 'handler', 'response', 'permission',
    'cooldown_seconds', 'user_cooldown_seconds', 'is_enabled',
    'reply_in_thread', 'usage_count', 'last_used_at', 'created_by',
])]
class Command extends Model
{
    /** @use HasFactory<CommandFactory> */
    use HasFactory;

    public const TYPE_STATIC = 'static';

    public const TYPE_DYNAMIC = 'dynamic';

    public const PERMISSION_EVERYONE = 'everyone';

    public const PERMISSION_SUBSCRIBER = 'subscriber';

    public const PERMISSION_MODERATOR = 'moderator';

    public const PERMISSION_BROADCASTER = 'broadcaster';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cooldown_seconds' => 'integer',
            'user_cooldown_seconds' => 'integer',
            'is_enabled' => 'boolean',
            'reply_in_thread' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CommandAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(CommandAlias::class);
    }

    /**
     * @return HasMany<CommandLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(CommandLog::class);
    }

    /**
     * @param  Builder<Command>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }
}
