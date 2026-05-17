<?php

namespace App\Models;

use Database\Factories\LivestreamEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event', 'is_live', 'title', 'category', 'viewer_count', 'payload', 'occurred_at',
])]
class LivestreamEvent extends Model
{
    /** @use HasFactory<LivestreamEventFactory> */
    use HasFactory;

    public const EVENT_STATUS = 'status.updated';

    public const EVENT_METADATA = 'metadata.updated';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'viewer_count' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
