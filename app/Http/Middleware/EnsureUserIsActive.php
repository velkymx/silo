<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminates a disabled account's session on its next request. Login is also
 * refused at authentication time (LoginController); this middleware covers
 * sessions that were already alive when the admin flipped the switch.
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, 'This account has been disabled.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been disabled.',
            ]);
        }

        return $next($request);
    }
}
