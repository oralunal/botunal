<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    /**
     * Routes a Kick member with an incomplete profile may still reach so they
     * can finish (or abandon) the profile.
     *
     * @var array<int, string>
     */
    private const array EXEMPT_ROUTES = ['account.edit', 'account.update', 'logout'];

    /**
     * Handle an incoming request.
     *
     * Only Kick members (non-null kick_user_id) with an incomplete profile are
     * redirected to the profile form. Normal/password/super-admin accounts have
     * a null kick_user_id and are unaffected.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->kick_user_id !== null
            && ! $user->isProfileComplete()
            && ! in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)
        ) {
            return redirect()->route('account.edit');
        }

        return $next($request);
    }
}
