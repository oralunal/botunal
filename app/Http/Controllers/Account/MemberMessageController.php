<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\MemberMessageStoreRequest;
use App\Models\MemberMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberMessageController extends Controller
{
    /**
     * Show the member's own messages and the compose form.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('account/Messages', [
            'messages' => $request->user()->memberMessages()->latest()->paginate(20)->withQueryString(),
        ]);
    }

    /**
     * Store a new message sent by the member to the broadcaster.
     */
    public function store(MemberMessageStoreRequest $request): RedirectResponse
    {
        MemberMessage::create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_read' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message sent.')]);

        return to_route('account.messages.index');
    }
}
