<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak permintaan dari akun yang diblokir admin (PRD §3).
 *
 * Logout tetap diizinkan agar pengguna yang diblokir bisa mengakhiri sesinya.
 */
class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBanned() && ! $request->is('api/auth/logout')) {
            abort(403, 'Akun kamu sedang diblokir. Hubungi admin.');
        }

        return $next($request);
    }
}
