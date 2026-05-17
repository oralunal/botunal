<?php

namespace App\Models;

use Database\Factories\TimerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'message', 'interval_seconds', 'min_messages_between',
    'only_when_live', 'is_enabled', 'last_run_at',
])]
class Timer extends Model
{
    /** @use HasFactory<TimerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_seconds' => 'integer',
            'min_messages_between' => 'integer',
            'only_when_live' => 'boolean',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Timer>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * Whether enough time has elapsed since the timer last fired.
     */
    public function isDue(): bool
    {
        return $this->last_run_at === null
            || $this->last_run_at->addSeconds($this->interval_seconds)->isPast();
    }
}
