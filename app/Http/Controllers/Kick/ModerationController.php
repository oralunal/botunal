<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kick\BanRequest;
use App\Models\ChatMessage;
use App\Models\KickBan;
use App\Services\Kick\KickApiClient;
use App\Services\Kick\KickResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ModerationController extends Controller
{
    public function __construct(
        private readonly KickApiClient $api,
        private readonly KickResolver $resolver,
    ) {}

    public function index(): Response
    {
        return Inertia::render('kick/Moderation', [
            'recent_bans' => KickBan::query()
                ->latest('occurred_at')
                ->limit(50)
                ->get(['id', 'target_username', 'moderator_username', 'action', 'reason', 'expires_at', 'source', 'occurred_at']),
        ]);
    }

    /**
     * Ban (or timeout, when a duration is given) a user.
     */
    public function ban(BanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $this->resolver->resolveUserId($data['target']);

        if ($userId === null) {
            return $this->fail(__('Could not resolve a user id. Provide a numeric id or a user who has chatted.'));
        }

        $duration = $data['duration_minutes'] ?? null;

        try {
            $this->api->banUser($this->resolver->broadcasterUserId(), $userId, $duration, $data['reason'] ?? null);
        } catch (Throwable $e) {
            report($e);

            return $this->fail(__('Kick rejected the ban: :msg', ['msg' => $e->getMessage()]));
        }

        KickBan::create([
            'target_kick_user_id' => $userId,
            'target_username' => ltrim($data['target'], '@'),
            'moderator_username' => $request->user()?->name,
            'action' => $duration !== null ? KickBan::ACTION_TIMEOUT : KickBan::ACTION_BAN,
            'reason' => $data['reason'] ?? null,
            'expires_at' => $duration !== null ? now()->addMinutes((int) $duration) : null,
            'source' => KickBan::SOURCE_DASHBOARD,
            'occurred_at' => now(),
        ]);

        return $this->ok(__('User banned.'));
    }

    /**
     * Remove a ban or timeout.
     */
    public function unban(Request $request): RedirectResponse
    {
        $target = (string) $request->input('target');
        $userId = $this->resolver->resolveUserId($target);

        if ($userId === null) {
            return $this->fail(__('Could not resolve a user id.'));
        }

        try {
            $this->api->unbanUser($this->resolver->broadcasterUserId(), $userId);
        } catch (Throwable $e) {
            report($e);

            return $this->fail(__('Kick rejected the unban: :msg', ['msg' => $e->getMessage()]));
        }

        KickBan::create([
            'target_kick_user_id' => $userId,
            'target_username' => ltrim($target, '@'),
            'moderator_username' => $request->user()?->name,
            'action' => KickBan::ACTION_UNBAN,
            'source' => KickBan::SOURCE_DASHBOARD,
            'occurred_at' => now(),
        ]);

        return $this->ok(__('User unbanned.'));
    }

    /**
     * Delete a single chat message.
     */
    public function deleteMessage(Request $request): RedirectResponse
    {
        $messageId = (string) $request->input('message_id');

        try {
            $this->api->deleteChatMessage($messageId);
        } catch (Throwable $e) {
            report($e);

            return $this->fail(__('Kick rejected the deletion: :msg', ['msg' => $e->getMessage()]));
        }

        ChatMessage::where('kick_message_id', $messageId)->update(['deleted_at' => now()]);

        return $this->ok(__('Message deleted.'));
    }

    private function ok(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
