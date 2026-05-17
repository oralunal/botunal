<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kick_user_id', 'previous_username', 'new_username', 'changed_at',
])]
class KickUserNameChange extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kick_user_id' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KickUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(KickUser::class, 'kick_user_id', 'kick_user_id');
    }
}
