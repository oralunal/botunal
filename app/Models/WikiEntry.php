<?php

namespace App\Models;

use App\Services\Kick\WikiText;
use Database\Factories\WikiEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'name_en', 'name_tr', 'slug', 'owner', 'role',
    'description_tr', 'description_en', 'is_enabled', 'is_curated', 'source_url',
])]
class WikiEntry extends Model
{
    /** @use HasFactory<WikiEntryFactory> */
    use HasFactory;

    public const TYPE_KILLER = 'killer';

    public const TYPE_SURVIVOR = 'survivor';

    public const TYPE_PERK = 'perk';

    public const TYPE_POWER = 'power';

    public const TYPE_ADDON = 'addon';

    public const TYPE_TERM = 'term';

    public const TYPES = [
        self::TYPE_KILLER, self::TYPE_SURVIVOR, self::TYPE_PERK,
        self::TYPE_POWER, self::TYPE_ADDON, self::TYPE_TERM,
    ];

    /** @var list<string> */
    protected $hidden = ['name_tr_norm'];

    public const ROLE_SURVIVOR = 'survivor';

    public const ROLE_KILLER = 'killer';

    protected static function booted(): void
    {
        static::saving(function (WikiEntry $entry): void {
            $entry->name_tr_norm = $entry->name_tr !== null
                ? WikiText::normalize($entry->name_tr)
                : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_curated' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WikiAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(WikiAlias::class);
    }

    /**
     * @param  Builder<WikiEntry>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function displayName(): string
    {
        return $this->name_tr ?: $this->name_en;
    }
}
