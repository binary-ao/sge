<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos HTTP de segurança aplicados a todas as respostas web.
 */
class CabecalhosSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        $resposta->headers->set('X-Frame-Options', 'SAMEORIGIN');                 // impede embeber o sistema noutros sítios (clickjacking)
        $resposta->headers->set('X-Content-Type-Options', 'nosniff');
        $resposta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $resposta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $resposta->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // Páginas autenticadas não ficam guardadas em cache partilhada nem no histórico do navegador.
        if ($request->user() && ! $resposta->headers->has('Content-Disposition')) {
            $resposta->headers->set('Cache-Control', 'no-store, private');
        }

        return $resposta;
    }
}
