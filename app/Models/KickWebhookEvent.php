<?php

namespace App\Models;

use Database\Factories\KickWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'message_id', 'event_type', 'event_version', 'kick_timestamp',
    'payload', 'headers', 'processed_at', 'process_error',
])]
class KickWebhookEvent extends Model
{
    /** @use HasFactory<KickWebhookEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'kick_timestamp' => 'datetime',
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Mark this event as successfully processed.
     */
    public function markProcessed(): void
    {
        $this->forceFill([
            'processed_at' => now(),
            'process_error' => null,
        ])->save();
    }

    /**
     * Record a processing failure for this event.
     */
    public function markFailed(string $error): void
    {
        $this->forceFill(['process_error' => $error])->save();
    }
}
