<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatMessageController extends Controller
{
    /**
     * Paginated, filterable chat message log.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'username' => $request->string('username')->trim()->toString() ?: null,
            'contains' => $request->string('contains')->trim()->toString() ?: null,
            'date' => $request->date('date'),
        ];

        $messages = ChatMessage::query()
            ->when($filters['username'], fn ($q, $username) => $q->where('sender_username', 'like', "%{$username}%"))
            ->when($filters['contains'], fn ($q, $contains) => $q->where('content', 'like', "%{$contains}%"))
            ->when($filters['date'], fn ($q, $date) => $q->whereDate('sent_at', $date))
            ->latest('sent_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('kick/Messages', [
            'messages' => $messages,
            'filters' => [
                'username' => $filters['username'],
                'contains' => $filters['contains'],
                'date' => $filters['date']?->toDateString(),
            ],
        ]);
    }
}
