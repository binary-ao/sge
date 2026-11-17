<?php

namespace App\Services\Avaliacao;

use App\Models\Classe;
use App\Models\ConfiguracaoAvaliacao;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\PeriodoLancamento;
use App\Models\ResultadoAvaliacao;
use App\Models\ResultadoFinal;
use App\Models\Turma;
use Illuminate\Support\Collection;

/**
 * Dados das pautas (trimestral, final, por disciplina, por classe e boletim individual).
 * Usados pelas vistas HTML, PDF e Excel — os três formatos apresentam os mesmos valores.
 */
class ConstrutorPautas
{
    public function __construct(private ServicoConfiguracao $configuracoes) {}

    /** Pauta da turma: $periodo 1–3 (trimestral) ou 0 (final). */
    public function turma(Turma $turma, int $periodo): array
    {
        $turma->loadMissing('escola.municipio', 'classe.nivelEnsino', 'curso', 'anoLectivo', 'turno', 'directorTurma');
        $disciplinas = $turma->planoCurricular()?->disciplinas ?? collect();
        $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id);
        $matriculas = $this->matriculas($turma);

        $resultados = ResultadoAvaliacao::semFiltroEscola()->whereIn('matricula_id', $matriculas->pluck('id'))->where('periodo', $periodo)
            ->get()->groupBy('matricula_id')->map(fn ($g) => $g->keyBy('disciplina_id'));
        $finais = $periodo === 0
            ? ResultadoFinal::semFiltroEscola()->whereIn('matricula_id', $matriculas->pluck('id'))->get()->keyBy('matricula_id')
            : collect();

        $linhas = $matriculas->values()->map(fn (Matricula $m, $i) => [
            'numero' => $i + 1,
            'matricula' => $m,
            'aluno' => $m->aluno,
            'notas' => $disciplinas->mapWithKeys(fn ($d) => [$d->id => $resultados[$m->id][$d->id] ?? null])->all(),
            'final' => $finais[$m->id] ?? null,
        ]);

        $estatisticas = $disciplinas->mapWithKeys(function ($d) use ($resultados, $matriculas) {
            $rs = $matriculas->map(fn ($m) => $resultados[$m->id][$d->id] ?? null)->filter(fn ($r) => $r && $r->classificacao !== null);

            return [$d->id => [
                'positivas' => $rs->where('situacao', 'positiva')->count(),
                'negativas' => $rs->where('situacao', 'negativa')->count(),
                'media' => $rs->count() ? round($rs->avg('classificacao'), 1) : null,
            ]];
        })->all();

        $situacoes = $finais->groupBy('situacao')->map->count();

        return [
            'tipo' => $periodo === 0 ? 'final' : 'trimestral',
            'titulo' => $periodo === 0 ? 'Pauta Final' : 'Pauta do '.PeriodoLancamento::PERIODOS[$periodo],
            'periodo' => $periodo,
            'turma' => $turma,
            'escola' => $turma->escola,
            'config' => $config,
            'disciplinas' => $disciplinas,
            'linhas' => $linhas,
            'estatisticas' => $estatisticas,
            'situacoes' => $situacoes,
            'desactualizados' => $this->desactualizados($resultados->flatten(1), $config),
            'fechado' => $periodo > 0 ? PeriodoLancamento::estaFechado($turma->escola_id, $turma->ano_lectivo_id, $periodo) : null,
            'gerado_em' => now(),
        ];
    }

    /** Pauta de uma disciplina: trimestres, exames e classificação final, com os valores intermédios. */
    public function disciplina(Turma $turma, Disciplina $disciplina): array
    {
        $turma->loadMissing('escola.municipio', 'classe.nivelEnsino', 'curso', 'anoLectivo', 'turno');
        $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id, $disciplina->id);
        $config?->loadMissing('formulas', 'componentes', 'escala');
        $matriculas = $this->matriculas($turma);
        $resultados = ResultadoAvaliacao::semFiltroEscola()->whereIn('matricula_id', $matriculas->pluck('id'))
            ->where('disciplina_id', $disciplina->id)->get()->groupBy('matricula_id')->map(fn ($g) => $g->keyBy('periodo'));

        $intermedias = $config ? $config->formulas->where('nivel', 'trimestral')->pluck('codigo')->reject(fn ($c) => $c === $config->variavel_trimestral)->values() : collect();
        $anuais = $config ? $config->formulas->where('nivel', 'anual')->pluck('codigo')->reject(fn ($c) => $c === $config->variavel_final)->values() : collect();
        $exames = $config ? $config->componentes->where('momento', 'exame')->pluck('codigo')->values() : collect();

        return [
            'tipo' => 'disciplina',
            'titulo' => 'Pauta da disciplina — '.$disciplina->nome,
            'turma' => $turma,
            'escola' => $turma->escola,
            'disciplina' => $disciplina,
            'config' => $config,
            'intermedias' => $intermedias,
            'anuais' => $anuais,
            'exames' => $exames,
            'linhas' => $matriculas->values()->map(fn ($m, $i) => [
                'numero' => $i + 1, 'matricula' => $m, 'aluno' => $m->aluno, 'resultados' => $resultados[$m->id] ?? collect(),
            ]),
            'desactualizados' => $this->desactualizados($resultados->flatten(1), $config),
            'gerado_em' => now(),
        ];
    }

    /** Resultados finais de todas as turmas de uma classe na escola activa. */
    public function classe(Classe $classe, int $anoLectivoId): array
    {
        $turmas = Turma::with('curso')->where('ano_lectivo_id', $anoLectivoId)->where('classe_id', $classe->id)->orderBy('nome')->get();
        $matriculas = Matricula::with('aluno', 'turma')->whereIn('turma_id', $turmas->pluck('id'))->whereIn('estado', ['activa', 'concluida'])->get();
        $finais = ResultadoFinal::whereIn('matricula_id', $matriculas->pluck('id'))->get()->keyBy('matricula_id');

        return [
            'classe' => $classe,
            'turmas' => $turmas->map(fn ($t) => [
                'turma' => $t,
                'linhas' => $matriculas->where('turma_id', $t->id)->sortBy('aluno.nome')->values()->map(fn ($m) => ['matricula' => $m, 'final' => $finais[$m->id] ?? null]),
            ]),
            'resumo' => $finais->groupBy('situacao')->map->count(),
            'total' => $matriculas->count(),
        ];
    }

    /** Boletim individual: classificações por disciplina e período, e situação final. */
    public function boletim(Matricula $matricula): array
    {
        $plano = \App\Models\PlanoCurricular::resolver($matricula->escola_id, $matricula->ano_lectivo_id, $matricula->classe_id, $matricula->curso_id);
        $resultados = ResultadoAvaliacao::semFiltroEscola()->where('matricula_id', $matricula->id)->get()->groupBy('disciplina_id')->map(fn ($g) => $g->keyBy('periodo'));

        return [
            'disciplinas' => $plano?->disciplinas ?? collect(),
            'resultados' => $resultados,
            'final' => ResultadoFinal::semFiltroEscola()->where('matricula_id', $matricula->id)->first(),
            'config' => $this->configuracoes->resolver($matricula->ano_lectivo_id, $matricula->classe_id),
        ];
    }

    /** Apresentação de uma classificação conforme o tipo de classificação da configuração. */
    public static function exibir(?ResultadoAvaliacao $r, ?ConfiguracaoAvaliacao $config, bool $html = true): string
    {
        if (! $r || $r->classificacao === null) {
            return '—';
        }
        $numero = rtrim(rtrim(number_format($r->classificacao, 2, ',', ''), '0'), ',');

        return match ($config?->tipo_classificacao) {
            'qualitativa' => (string) $r->classificacao_qualitativa,
            'mista' => $html ? $numero.' <small class="text-secondary">'.e($r->classificacao_qualitativa).'</small>' : $numero.' ('.$r->classificacao_qualitativa.')',
            default => $numero,
        };
    }

    private function matriculas(Turma $turma): Collection
    {
        return Matricula::semFiltroEscola()->with('aluno', 'classe')->where('turma_id', $turma->id)
            ->whereIn('estado', ['activa', 'concluida'])->get()->sortBy('aluno.nome')->values();
    }

    private function desactualizados(Collection $resultados, ?ConfiguracaoAvaliacao $config): int
    {
        if (! $config) {
            return 0;
        }

        return $resultados->filter(fn ($r) => $r && $r->configuracao_avaliacao_id === $config->id && $r->configuracao_versao < $config->versao)->count();
    }
}
