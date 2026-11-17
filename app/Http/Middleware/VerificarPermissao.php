<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('permissao:escolas.gerir') ou 'permissao:escolas.ver|escolas.gerir' (qualquer uma).
 */
class VerificarPermissao
{
    public function handle(Request $request, Closure $next, string $permissoes): Response
    {
        $user = $request->user();

        $autorizado = $user && collect(explode('|', $permissoes))
            ->contains(fn ($p) => $user->temPermissao(trim($p)));

        abort_unless($autorizado, 403, 'Não tem permissão para aceder a esta funcionalidade.');

        return $next($request);
    }
}
