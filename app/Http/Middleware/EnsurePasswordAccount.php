<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to password-based accounts (legacy admins). Kick members
 * authenticate via OAuth and have no password, so the security and password
 * routes are meaningless for them and must return 403.
 */
class EnsurePasswordAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->kick_user_id !== null) {
            abort(403);
        }

        return $next($request);
    }
}
