<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TimerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'message', 'position', 'interval_seconds', 'min_messages_between',
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
            'position' => 'integer',
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
     * Timers take turns by position, falling back to creation order.
     *
     * @param  Builder<Timer>  $query
     */
    public function scopeInRotationOrder(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * Whether this timer takes its turn after the given timer in the rotation.
     */
    public function comesAfter(Timer $other): bool
    {
        return $this->position === $other->position
            ? $this->id > $other->id
            : $this->position > $other->position;
    }

    /**
     * Whether the timer has waited its interval since the previous timer message.
     */
    public function isDueAfter(CarbonInterface $previousRunAt): bool
    {
        return now()->subSeconds($this->interval_seconds)->greaterThanOrEqualTo($previousRunAt);
    }
}
