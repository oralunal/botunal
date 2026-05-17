<?php

namespace App\Models;

use Database\Factories\KickFollowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['follower_kick_user_id', 'follower_username', 'followed_at'])]
class KickFollow extends Model
{
    /** @use HasFactory<KickFollowFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'follower_kick_user_id' => 'integer',
            'followed_at' => 'datetime',
        ];
    }
}
