<?php

namespace App\Models;

use Database\Factories\RewardRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kick_redemption_id', 'reward_title', 'reward_cost', 'redeemer_kick_user_id',
    'redeemer_username', 'user_input', 'status', 'redeemed_at',
])]
class RewardRedemption extends Model
{
    /** @use HasFactory<RewardRedemptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reward_cost' => 'integer',
            'redeemer_kick_user_id' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }
}
