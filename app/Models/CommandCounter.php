<?php

namespace App\Models;

use Database\Factories\CommandCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class CommandCounter extends Model
{
    /** @use HasFactory<CommandCounterFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    /**
     * Atomically increment a named counter and return its new value.
     */
    public static function bump(string $key, int $by = 1): int
    {
        $counter = static::firstOrCreate(['key' => $key], ['value' => 0]);
        $counter->increment('value', $by);

        return $counter->value;
    }
}
