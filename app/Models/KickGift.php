<?php

namespace App\Models;

use Database\Factories\KickGiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'sender_kick_user_id', 'sender_username', 'recipient_username',
    'gift_name', 'kicks_amount', 'message', 'occurred_at',
])]
class KickGift extends Model
{
    /** @use HasFactory<KickGiftFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sender_kick_user_id' => 'integer',
            'kicks_amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
