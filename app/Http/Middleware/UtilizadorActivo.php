<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Termina a sessão de utilizadores desactivados enquanto estavam autenticados.
 */
class UtilizadorActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'A sua conta encontra-se desactivada. Contacte o administrador.']);
        }

        return $next($request);
    }
}
