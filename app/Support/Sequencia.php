<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Numeração sequencial por escola, segura em concorrência (bloqueio de linha).
 * Usada para processos de alunos, matrículas e, nas fases seguintes, recibos e declarações.
 */
class Sequencia
{
    public static function proximo(int $escolaId, string $tipo, int $ano = 0): int
    {
        return DB::transaction(function () use ($escolaId, $tipo, $ano) {
            DB::table('sequencias')->insertOrIgnore(['escola_id' => $escolaId, 'tipo' => $tipo, 'ano' => $ano, 'ultimo' => 0]);

            $linha = DB::table('sequencias')
                ->where(['escola_id' => $escolaId, 'tipo' => $tipo, 'ano' => $ano])
                ->lockForUpdate()->first();

            $novo = $linha->ultimo + 1;
            DB::table('sequencias')->where('id', $linha->id)->update(['ultimo' => $novo]);

            return $novo;
        });
    }

    /** Ex.: formatar(2026, 15) => "2026-00015" */
    public static function formatar(int $ano, int $numero, int $digitos = 5): string
    {
        return ($ano ? $ano.'-' : '').str_pad((string) $numero, $digitos, '0', STR_PAD_LEFT);
    }
}
