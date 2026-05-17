<?php

namespace App\Models;

use Database\Factories\KickEventSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kick_subscription_id', 'event_name', 'event_version', 'method',
    'broadcaster_user_id', 'status', 'last_synced_at',
])]
class KickEventSubscription extends Model
{
    /** @use HasFactory<KickEventSubscriptionFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DELETED = 'deleted';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'broadcaster_user_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
}
