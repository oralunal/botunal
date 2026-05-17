<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kick\TimerStoreRequest;
use App\Http\Requests\Kick\TimerUpdateRequest;
use App\Models\Timer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TimerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('kick/Timers', [
            'timers' => Timer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(TimerStoreRequest $request): RedirectResponse
    {
        Timer::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Timer created.')]);

        return to_route('kick.timers.index');
    }

    public function update(TimerUpdateRequest $request, Timer $timer): RedirectResponse
    {
        $timer->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Timer updated.')]);

        return to_route('kick.timers.index');
    }

    public function destroy(Timer $timer): RedirectResponse
    {
        $timer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Timer deleted.')]);

        return to_route('kick.timers.index');
    }
}
