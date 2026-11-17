<?php

namespace App\Services\Administrativo;

use App\Models\AnoLectivo;
use App\Models\Falta;
use App\Models\Matricula;
use App\Models\PlanoCurricular;
use Illuminate\Support\Carbon;

/**
 * Frequência do aluno = 1 − (tempos em falta contabilizados ÷ aulas previstas).
 * Aulas previstas = carga horária semanal do plano curricular × semanas lectivas decorridas.
 *
 * Se a turma não tiver qualquer registo de faltas, a assiduidade não está a ser registada
 * e a frequência fica "não verificada" (null) — o sistema não assume 100%.
 */
class ServicoFrequencia
{
    public function resumo(Matricula $m, ?Carbon $ate = null): array
    {
        $faltas = Falta::semFiltroEscola()->where('matricula_id', $m->id)->get();
        $contarJustificadas = config('administrativo.faltas.contar_justificadas', false);
        $contadas = $faltas->filter(fn ($f) => $contarJustificadas || $f->justificacao !== 'aprovado')->sum('tempos');
        $previstas = $this->aulasPrevistas($m, $ate);
        $registada = $m->turma_id && Falta::semFiltroEscola()->where('turma_id', $m->turma_id)->exists();

        return [
            'faltas' => $faltas->count(),
            'tempos' => (int) $faltas->sum('tempos'),
            'justificadas' => (int) $faltas->where('justificacao', 'aprovado')->sum('tempos'),
            'pendentes' => $faltas->where('justificacao', 'pendente')->count(),
            'contabilizadas' => (int) $contadas,
            'aulas_previstas' => $previstas,
            'frequencia' => $registada && $previstas > 0 ? max(0.0, round((1 - $contadas / $previstas) * 100, 1)) : null,
            'registada' => $registada,
        ];
    }

    public function percentagem(Matricula $m): ?float
    {
        return $this->resumo($m)['frequencia'];
    }

    public function aulasPrevistas(Matricula $m, ?Carbon $ate = null): int
    {
        $plano = PlanoCurricular::resolver($m->escola_id, $m->ano_lectivo_id, $m->classe_id, $m->curso_id);
        $carga = (int) ($plano?->disciplinas->sum('pivot.carga_horaria') ?? 0);

        return $carga * $this->semanasDecorridas($m->anoLectivo ?? AnoLectivo::find($m->ano_lectivo_id), $ate);
    }

    /** Semanas lectivas decorridas (limitadas a 3 trimestres × semanas por trimestre). */
    public function semanasDecorridas(AnoLectivo $ano, ?Carbon $ate = null): int
    {
        $ate = ($ate ?? now())->copy()->min($ano->data_fim);
        if ($ate->lt($ano->data_inicio)) {
            return 0;
        }
        $maximo = 3 * (int) config('administrativo.faltas.semanas_por_trimestre', 12);

        return min($maximo, (int) ceil($ano->data_inicio->diffInDays($ate) / 7));
    }
}
