<?php

namespace App\Http\Controllers\Academico;

use App\Support\ContextoEscola;
use Illuminate\Http\Request;

/**
 * Regras de edição da configuração académica:
 *  - registos de referência provincial (escola_id null) exigem "academico.global";
 *  - registos da escola exigem "academico.configurar", escrita operacional e que
 *    pertençam à escola activa.
 */
trait AutorizaConfiguracao
{
    public function podeEditar(?int $escolaIdDoRegisto): bool
    {
        $u = auth()->user();
        $ctx = app(ContextoEscola::class);

        if ($escolaIdDoRegisto === null) {
            return $u->can('academico.global');
        }

        return $u->can('academico.configurar')
            && $ctx->podeEscreverDadosOperacionais()
            && $ctx->escolaActivaId() === $escolaIdDoRegisto;
    }

    protected function exigirEdicao(?int $escolaIdDoRegisto): void
    {
        abort_unless($this->podeEditar($escolaIdDoRegisto), 403,
            $escolaIdDoRegisto === null
                ? 'Os registos de referência provincial só podem ser alterados pela administração central.'
                : 'Não tem permissão para alterar a configuração desta escola.');
    }

    /** Escola a que pertence um novo registo (null = referência provincial). */
    protected function escolaDestino(Request $request): ?int
    {
        $ctx = app(ContextoEscola::class);
        $escolaId = $ctx->escolaActivaId();

        // Sem escola activa, a pedido, ou quando o utilizador só pode criar referências
        // (ex.: Direcção Provincial dentro de uma escola), o registo é de referência provincial.
        if ($request->boolean('referencia_provincial') || ! $escolaId || ! $this->podeEditar($escolaId)) {
            $this->exigirEdicao(null);

            return null;
        }
        $this->exigirEdicao($escolaId);

        return $escolaId;
    }
}
