<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\CommandLog;
use Inertia\Inertia;
use Inertia\Response;

class CommandLogController extends Controller
{
    /**
     * Paginated command activity log.
     */
    public function index(): Response
    {
        return Inertia::render('kick/CommandLogs', [
            'logs' => CommandLog::query()
                ->latest('occurred_at')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
