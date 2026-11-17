<?php

namespace App\Services\Avaliacao\Formula;

use RuntimeException;

/**
 * Uma variável necessária ao cálculo ainda não tem valor (ex.: prova trimestral não lançada).
 * Não é um erro da fórmula: o resultado fica "incompleto".
 */
class ValorEmFalta extends RuntimeException
{
    public function __construct(public readonly string $variavel)
    {
        parent::__construct("Valor em falta: {$variavel}");
    }
}
