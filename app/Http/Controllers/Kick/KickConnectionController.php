<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\KickConnection;
use App\Services\Kick\KickScopes;
use Inertia\Inertia;
use Inertia\Response;

class KickConnectionController extends Controller
{
    /**
     * Show the connection status for the channel and bot accounts.
     */
    public function index(): Response
    {
        return Inertia::render('kick/Connections', [
            'channel' => $this->present(KickConnection::channel(), KickConnection::TYPE_CHANNEL),
            'bot' => $this->present(KickConnection::bot(), KickConnection::TYPE_BOT),
        ]);
    }

    /**
     * Build a token-free view model for a connection.
     *
     * @return array<string, mixed>
     */
    private function present(?KickConnection $connection, string $type): array
    {
        $required = KickScopes::for($type);
        $granted = $connection?->scopes ?? [];

        return [
            'type' => $type,
            'connected' => $connection !== null,
            'slug' => $connection?->slug,
            'display_name' => $connection?->display_name,
            'kick_user_id' => $connection?->kick_user_id,
            'broadcaster_user_id' => $connection?->broadcaster_user_id,
            'connected_at' => $connection?->connected_at?->toIso8601String(),
            'expires_at' => $connection?->expires_at?->toIso8601String(),
            'is_expired' => $connection?->isExpired() ?? true,
            'required_scopes' => $required,
            'granted_scopes' => $granted,
            'missing_scopes' => array_values(array_diff($required, $granted)),
        ];
    }
}
