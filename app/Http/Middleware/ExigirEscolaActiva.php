<?php

namespace App\Http\Middleware;

use App\Support\ContextoEscola;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Os módulos operacionais (alunos, turmas, matrículas…) trabalham sempre
 * dentro de uma escola. Sem escola activa, o utilizador escolhe uma do seu âmbito.
 */
class ExigirEscolaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(ContextoEscola::class)->escolaActivaId()) {
            if ($request->expectsJson()) {
                abort(409, 'Seleccione primeiro uma escola.');
            }

            return redirect()->route('contexto.escolher')
                ->with('aviso', 'Para aceder a este módulo, entre primeiro na área de uma escola.');
        }

        return $next($request);
    }
}
