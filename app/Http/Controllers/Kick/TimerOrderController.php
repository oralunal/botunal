<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kick\TimerOrderUpdateRequest;
use App\Models\Timer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TimerOrderController extends Controller
{
    public function update(TimerOrderUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('ids') as $index => $timerId) {
                Timer::whereKey($timerId)->update(['position' => $index + 1]);
            }
        });

        return to_route('kick.timers.index');
    }
}
