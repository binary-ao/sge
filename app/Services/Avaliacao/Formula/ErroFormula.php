<?php

namespace App\Services\Avaliacao\Formula;

use RuntimeException;

/** Erro de sintaxe ou de semântica numa fórmula (mensagem em português, com posição). */
class ErroFormula extends RuntimeException
{
    public function __construct(string $mensagem, public readonly ?int $posicao = null)
    {
        parent::__construct($posicao !== null ? "{$mensagem} (posição {$posicao})" : $mensagem);
    }
}
