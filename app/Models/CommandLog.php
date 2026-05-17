<?php

namespace App\Models;

use Database\Factories\CommandLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'command_id', 'alias_used', 'invoker_username', 'invoker_kick_user_id',
    'raw_message', 'response_sent', 'outcome', 'occurred_at',
])]
class CommandLog extends Model
{
    /** @use HasFactory<CommandLogFactory> */
    use HasFactory;

    public const OUTCOME_SENT = 'sent';

    public const OUTCOME_COOLDOWN = 'cooldown';

    public const OUTCOME_DENIED = 'denied';

    public const OUTCOME_ERROR = 'error';

    public const OUTCOME_DISABLED = 'disabled';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoker_kick_user_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
