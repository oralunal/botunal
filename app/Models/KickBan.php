<?php

namespace App\Models;

use Database\Factories\KickBanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'target_kick_user_id', 'target_username', 'moderator_username',
    'action', 'reason', 'expires_at', 'source', 'occurred_at',
])]
class KickBan extends Model
{
    /** @use HasFactory<KickBanFactory> */
    use HasFactory;

    public const ACTION_BAN = 'ban';

    public const ACTION_TIMEOUT = 'timeout';

    public const ACTION_UNBAN = 'unban';

    public const SOURCE_WEBHOOK = 'webhook';

    public const SOURCE_DASHBOARD = 'dashboard';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_kick_user_id' => 'integer',
            'expires_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }
}
