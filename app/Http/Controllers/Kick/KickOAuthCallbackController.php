<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Auth\KickAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public dispatcher for the single Kick OAuth callback URL. Kick only allows
 * one redirect_uri, so both the admin connection flow and the member login
 * flow share this endpoint. The cached state payload's `purpose` decides
 * which controller actually handles the callback.
 */
class KickOAuthCallbackController extends Controller
{
    /**
     * Peek at the cached OAuth state and forward to the correct handler.
     *
     * The cache entry is read with Cache::get (a non-consuming peek) so the
     * downstream controller's own Cache::pull still resolves the verifier.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $state = $request->string('state')->toString();

        $cached = Cache::get("kick:oauth:{$state}");

        if (is_array($cached) && ($cached['purpose'] ?? null) === 'member-login') {
            return app(KickAuthController::class)->callback($request);
        }

        return app(KickOAuthController::class)->callback($request);
    }
}
