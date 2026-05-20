<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft-redirect for Kick OAuth members away from the password-admin /settings/*
 * area to the equivalent /account/* page. Used on routes that have an account
 * counterpart (profile, appearance). Routes with no Kick-member equivalent
 * should use EnsurePasswordAccount (hard 403) instead.
 */
class RedirectKickToAccount
{
    public function handle(Request $request, Closure $next, string $routeName = 'account.edit'): Response
    {
        if ($request->user()?->kick_user_id !== null) {
            return redirect()->route($routeName);
        }

        return $next($request);
    }
}
