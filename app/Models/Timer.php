<?php

namespace App\Models;

use Carbon\CarbonInterface;
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
     * Timers take turns in creation order.
     *
     * @param  Builder<Timer>  $query
     */
    public function scopeInRotationOrder(Builder $query): void
    {
        $query->orderBy('id');
    }

    /**
     * Whether the timer has waited its interval since the previous timer message.
     */
    public function isDueAfter(CarbonInterface $previousRunAt): bool
    {
        return now()->subSeconds($this->interval_seconds)->greaterThanOrEqualTo($previousRunAt);
    }
}
