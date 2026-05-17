<?php

namespace App\Models;

use Database\Factories\CommandAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['command_id', 'alias'])]
class CommandAlias extends Model
{
    /** @use HasFactory<CommandAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
