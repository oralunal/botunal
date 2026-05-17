<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kick_message_id', 'sender_kick_user_id', 'sender_username', 'sender_identity',
    'content', 'is_command', 'replied_to_message_id', 'deleted_at', 'sent_at',
])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
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
            'sender_identity' => 'array',
            'is_command' => 'boolean',
            'deleted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
