<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\MemberMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $unread = $request->boolean('unread');

        $messages = MemberMessage::query()
            ->with('user:id,name,first_name,last_name,email,kick_username')
            ->when($unread, fn ($q) => $q->where('is_read', false))
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (MemberMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
                'user' => $message->user === null ? null : [
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                    'first_name' => $message->user->first_name,
                    'last_name' => $message->user->last_name,
                    'email' => $message->user->email,
                    'kick_username' => $message->user->kick_username,
                ],
            ]);

        return Inertia::render('kick/MemberMessages', [
            'messages' => $messages,
            'filters' => ['unread' => $unread],
        ]);
    }

    public function markRead(MemberMessage $memberMessage): RedirectResponse
    {
        $memberMessage->is_read = true;
        $memberMessage->read_at = now();
        $memberMessage->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as read.')]);

        return back();
    }

    public function markUnread(MemberMessage $memberMessage): RedirectResponse
    {
        $memberMessage->is_read = false;
        $memberMessage->read_at = null;
        $memberMessage->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as unread.')]);

        return back();
    }
}
