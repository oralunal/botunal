<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Kick\KickIdentity;
use App\Services\Kick\KickPkce;
use App\Services\Kick\KickTokenManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Throwable;

/**
 * Member-facing Kick OAuth login. Members authenticate against Kick with a
 * minimal `user:read` scope so we can identify them; no channel/bot tokens
 * are stored here (that is the admin connection flow).
 */
class KickAuthController extends Controller
{
    public function __construct(
        private readonly KickTokenManager $tokens,
        private readonly KickIdentity $identity,
    ) {}

    /**
     * Begin the OAuth 2.1 + PKCE authorization flow for a member login.
     */
    public function redirect(): RedirectResponse
    {
        $verifier = KickPkce::generateVerifier();
        $state = KickPkce::state();

        Cache::put("kick:oauth:{$state}", [
            'purpose' => 'member-login',
            'verifier' => $verifier,
        ], now()->addMinutes(10));

        $query = http_build_query([
            'client_id' => config('services.kick.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.kick.redirect_uri'),
            'state' => $state,
            'scope' => 'user:read',
            'code_challenge' => KickPkce::challenge($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(config('services.kick.urls.authorize').'?'.$query);
    }

    /**
     * Handle the member OAuth callback: resolve the Kick identity and log the
     * member in. No Kick token or connection is persisted.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->fail($request->string('error_description', 'Kick authorization was denied.'));
        }

        $state = $request->string('state')->toString();
        $cached = Cache::pull("kick:oauth:{$state}");

        if ($cached === null
            || ($cached['purpose'] ?? null) !== 'member-login'
            || ! $request->filled('code')) {
            return $this->fail('Invalid or expired authorization request. Please try again.');
        }

        try {
            $tokens = $this->tokens->exchangeCode($request->string('code')->toString(), $cached['verifier']);
            $id = $this->identity->fetch($tokens['access_token']);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Could not complete the Kick login. Please try again.');
        }

        unset($tokens);

        if (empty($id['user_id'])) {
            return $this->fail('Could not read your Kick account.');
        }

        $user = User::firstOrNew(['kick_user_id' => $id['user_id']]);

        $user->kick_user_id = $id['user_id'];
        $user->kick_username = $id['name'];
        $user->name = $user->name ?: ($id['name'] ?: 'Kick Kullanıcısı');

        if (filled($id['email']) && blank($user->email)) {
            $user->email = $id['email'];
            $user->email_verified_at = now();
        }
        // When Kick exposes no email, users.email is left null (the column is
        // nullable + unique). isProfileComplete() then forces the member to
        // set a real address at /account before proceeding.

        $user->save();

        Auth::login($user, remember: true);

        if (! $user->isProfileComplete()) {
            return to_route('account.edit');
        }

        if ($user->isSuperAdmin() || $user->permissions()->exists()) {
            return redirect()->intended(route('kick.dashboard'));
        }

        return redirect()->intended(route('account.edit'));
    }

    /**
     * Flash an error toast and send the member back to the registration page.
     */
    private function fail(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('register');
    }
}
