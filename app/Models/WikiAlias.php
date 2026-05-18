<?php

namespace App\Models;

use Database\Factories\WikiAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wiki_entry_id', 'alias', 'alias_norm'])]
class WikiAlias extends Model
{
    /** @use HasFactory<WikiAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WikiEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(WikiEntry::class, 'wiki_entry_id');
    }
}
