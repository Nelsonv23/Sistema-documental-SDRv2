<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Revoca el acceso si una cuenta autenticada fue desactivada durante su sesión.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu cuenta está desactivada. Contacta al administrador del sistema.',
                ], 403);
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu cuenta está desactivada. Contacta al administrador del sistema.',
                ]);
        }

        return $next($request);
    }
}
