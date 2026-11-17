<?php

namespace App\Services\Avaliacao;

use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PlanoCurricular;
use App\Models\ResultadoAvaliacao;
use App\Models\ResultadoFinal;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcula e grava os resultados (por disciplina e finais) a partir das notas lançadas,
 * registando a configuração e a versão utilizadas em cada cálculo.
 */
class ServicoResultados
{
    public function __construct(
        private ServicoConfiguracao $configuracoes,
        private MotorCalculo $motor,
        private MotorRegras $regras,
    ) {}

    /** Notas do aluno numa disciplina, no formato de entrada do MotorCalculo. */
    public function notasPara(Matricula $matricula, int $disciplinaId): array
    {
        $linhas = Nota::semFiltroEscola()
            ->join('avaliacoes', 'avaliacoes.id', '=', 'notas.avaliacao_id')
            ->where('notas.matricula_id', $matricula->id)
            ->where('avaliacoes.disciplina_id', $disciplinaId)
            ->orderBy('avaliacoes.indice')
            ->get(['avaliacoes.periodo', 'avaliacoes.componente', 'notas.valor']);

        $entrada = [];
        foreach ($linhas as $l) {
            $entrada[$l->periodo][$l->componente][] = (float) $l->valor;
        }

        return $entrada;
    }

    /**
     * Recalcula uma disciplina para uma matrícula. Devolve o resultado do motor ou null
     * se não existir configuração aplicável.
     */
    public function recalcularDisciplina(Matricula $matricula, int $disciplinaId, ?int $utilizadorId = null, bool $actualizarFinal = true): ?array
    {
        $config = $this->configuracoes->resolver($matricula->ano_lectivo_id, $matricula->classe_id, $disciplinaId);
        if (! $config) {
            return null;
        }
        $definicao = $this->configuracoes->definicao($config);
        $calculo = $this->motor->calcular($definicao, $this->notasPara($matricula, $disciplinaId));
        $agora = now();

        $gravar = function (int $periodo, array $r) use ($matricula, $disciplinaId, $config, $utilizadorId, $agora) {
            ResultadoAvaliacao::semFiltroEscola()->updateOrInsert(
                ['matricula_id' => $matricula->id, 'disciplina_id' => $disciplinaId, 'periodo' => $periodo],
                [
                    'escola_id' => $matricula->escola_id,
                    'turma_id' => $matricula->turma_id,
                    'valores' => json_encode($r['valores']),
                    'classificacao' => $r['classificacao'],
                    'classificacao_qualitativa' => $r['qualitativa'],
                    'situacao' => $r['situacao'],
                    'configuracao_avaliacao_id' => $config->id,
                    'configuracao_versao' => $config->versao,
                    'calculado_em' => $agora,
                    'calculado_por' => $utilizadorId,
                ]
            );
        };

        DB::transaction(function () use ($calculo, $gravar) {
            foreach ($calculo['trimestres'] as $t => $r) {
                $gravar($t, $r);
            }
            $gravar(0, $calculo['final']);
        });

        if ($actualizarFinal) {
            $this->recalcularSituacaoFinal($matricula);
        }

        return $calculo;
    }

    /** Disciplinas do plano curricular aplicável à matrícula. */
    public function disciplinasDaMatricula(Matricula $matricula): Collection
    {
        $plano = PlanoCurricular::resolver($matricula->escola_id, $matricula->ano_lectivo_id, $matricula->classe_id, $matricula->curso_id);

        return $plano ? $plano->disciplinas()->withoutGlobalScope('escola')->get() : collect();
    }

    /** Situação final do aluno (aprovação/transição) com justificação. */
    public function recalcularSituacaoFinal(Matricula $matricula): ?ResultadoFinal
    {
        $config = $this->configuracoes->resolver($matricula->ano_lectivo_id, $matricula->classe_id);
        if (! $config) {
            return null;
        }
        $definicao = $this->configuracoes->definicao($config);
        $disciplinas = $this->disciplinasDaMatricula($matricula);
        $finais = ResultadoAvaliacao::semFiltroEscola()->where('matricula_id', $matricula->id)->where('periodo', 0)
            ->pluck('classificacao', 'disciplina_id');

        $lista = $disciplinas->map(fn (Disciplina $d) => [
            'nome' => $d->nome,
            'nuclear' => (bool) $d->nuclear,
            'especifica' => (bool) $d->pivot->especifica,
            'classificacao' => isset($finais[$d->id]) ? (float) $finais[$d->id] : null,
        ])->all();

        $regime = $matricula->classe?->regime ?? 'transicao';
        $r = $this->regras->avaliar($definicao->regras, $regime, $lista, $this->frequencia($matricula), $definicao->notaMinimaAprovacao);

        $resultado = ResultadoFinal::semFiltroEscola()->firstOrNew(['matricula_id' => $matricula->id]);
        $resultado->forceFill([
            'escola_id' => $matricula->escola_id,
            'ano_lectivo_id' => $matricula->ano_lectivo_id,
            'situacao' => $r['situacao'],
            'rotulo' => $r['rotulo'],
            'justificacao' => $r['justificacao'],
            'media' => $r['media'],
            'disciplinas_negativas' => $r['negativas'],
            'configuracao_avaliacao_id' => $config->id,
            'configuracao_versao' => $config->versao,
            'calculado_em' => now(),
        ])->save();

        return $resultado;
    }

    /**
     * Frequência do aluno (%) para a regra de frequência mínima, a partir do registo de faltas.
     * Devolve null se a assiduidade da turma não estiver a ser registada ("não verificada").
     */
    public function frequencia(Matricula $matricula): ?float
    {
        return app(\App\Services\Administrativo\ServicoFrequencia::class)->percentagem($matricula);
    }

    /** Recálculo controlado de uma turma (todas as disciplinas ou uma). Devolve o número de cálculos. */
    public function recalcularTurma(Turma $turma, ?int $disciplinaId = null, ?int $utilizadorId = null): int
    {
        $total = 0;
        // Inclui matrículas concluídas (anos anteriores); exclui anuladas, transferidas e desistentes.
        $matriculas = Matricula::semFiltroEscola()->with('classe')->where('turma_id', $turma->id)->whereIn('estado', ['activa', 'concluida'])->get();
        $plano = $turma->planoCurricular();
        $disciplinas = $disciplinaId ? [$disciplinaId] : ($plano?->disciplinas->pluck('id')->all() ?? []);

        foreach ($matriculas as $m) {
            foreach ($disciplinas as $d) {
                if ($this->recalcularDisciplina($m, $d, $utilizadorId, false)) {
                    $total++;
                }
            }
            $this->recalcularSituacaoFinal($m);
        }

        return $total;
    }
}
