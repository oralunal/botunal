<?php

namespace App\Models;

use Database\Factories\KickSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type', 'subscriber_kick_user_id', 'subscriber_username', 'gifter_username',
    'tier', 'duration', 'quantity', 'occurred_at',
])]
class KickSubscription extends Model
{
    /** @use HasFactory<KickSubscriptionFactory> */
    use HasFactory;

    public const TYPE_NEW = 'new';

    public const TYPE_RENEWAL = 'renewal';

    public const TYPE_GIFT = 'gift';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscriber_kick_user_id' => 'integer',
            'duration' => 'integer',
            'quantity' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
