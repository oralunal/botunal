<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\KickConnection;
use App\Services\Kick\KickApiClient;
use App\Services\Kick\KickPkce;
use App\Services\Kick\KickScopes;
use App\Services\Kick\KickTokenManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Throwable;

class KickOAuthController extends Controller
{
    public function __construct(
        private readonly KickTokenManager $tokens,
        private readonly KickApiClient $api,
    ) {}

    /**
     * Begin the OAuth 2.1 + PKCE authorization flow for a connection type.
     */
    public function redirect(string $type): RedirectResponse
    {
        abort_unless(in_array($type, [KickConnection::TYPE_CHANNEL, KickConnection::TYPE_BOT], true), 404);

        $verifier = KickPkce::generateVerifier();
        $state = KickPkce::state();

        Cache::put("kick:oauth:{$state}", [
            'type' => $type,
            'verifier' => $verifier,
        ], now()->addMinutes(10));

        $query = http_build_query([
            'client_id' => config('services.kick.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.kick.redirect_uri'),
            'state' => $state,
            'scope' => implode(' ', KickScopes::for($type)),
            'code_challenge' => KickPkce::challenge($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(config('services.kick.urls.authorize').'?'.$query);
    }

    /**
     * Handle the OAuth callback: exchange the code and persist the connection.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->fail($request->string('error_description', 'Kick authorization was denied.'));
        }

        $state = $request->string('state')->toString();
        $cached = Cache::pull("kick:oauth:{$state}");

        if ($cached === null || ! $request->filled('code')) {
            return $this->fail('Invalid or expired authorization request. Please try again.');
        }

        try {
            $tokens = $this->tokens->exchangeCode($request->string('code')->toString(), $cached['verifier']);

            $connection = KickConnection::updateOrCreate(
                ['type' => $cached['type']],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'scopes' => isset($tokens['scope']) ? explode(' ', $tokens['scope']) : KickScopes::for($cached['type']),
                    'token_type' => $tokens['token_type'] ?? 'Bearer',
                    'expires_at' => Carbon::now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                    'connected_at' => Carbon::now(),
                    'last_refreshed_at' => Carbon::now(),
                ],
            );

            $this->hydrateIdentity($connection);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Could not complete the Kick connection. Please try again.');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':type connection established.', ['type' => ucfirst($cached['type'])]),
        ]);

        return to_route('kick.connections');
    }

    /**
     * Remove a stored connection.
     */
    public function disconnect(string $type): RedirectResponse
    {
        abort_unless(in_array($type, [KickConnection::TYPE_CHANNEL, KickConnection::TYPE_BOT], true), 404);

        KickConnection::where('type', $type)->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':type connection removed.', ['type' => ucfirst($type)]),
        ]);

        return to_route('kick.connections');
    }

    /**
     * Resolve and store the Kick user id / slug (and broadcaster id for the channel).
     */
    private function hydrateIdentity(KickConnection $connection): void
    {
        $user = $this->api->currentUser($connection);

        $connection->forceFill([
            'kick_user_id' => $user['user_id'] ?? null,
            'display_name' => $user['name'] ?? null,
        ]);

        if ($connection->type === KickConnection::TYPE_CHANNEL) {
            $channel = $this->api->channelBySlug(config('services.kick.channel_slug'));
            $connection->forceFill([
                'slug' => config('services.kick.channel_slug'),
                'broadcaster_user_id' => $channel['broadcaster_user_id'] ?? $user['user_id'] ?? null,
            ]);
        } else {
            $connection->forceFill(['slug' => config('services.kick.bot_slug')]);
        }

        $connection->save();
    }

    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('kick.connections');
    }
}
