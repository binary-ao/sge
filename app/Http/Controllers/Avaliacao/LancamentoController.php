<?php

namespace App\Http\Controllers\Avaliacao;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Escola\RestringeProfessor;
use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Avaliacao;
use App\Models\ComponenteAvaliacao;
use App\Models\ConfiguracaoAvaliacao;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLancamento;
use App\Models\ResultadoAvaliacao;
use App\Models\Turma;
use App\Services\Avaliacao\ServicoConfiguracao;
use App\Services\Avaliacao\ServicoResultados;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LancamentoController extends Controller
{
    use RestringeProfessor;

    public function __construct(private ServicoConfiguracao $configuracoes, private ServicoResultados $resultados) {}

    /** Selecção de turma e disciplina. */
    public function index(Request $request, ContextoEscola $ctx): View
    {
        $ano = AnoLectivo::actual();
        $permitidas = $this->turmasPermitidas();
        $professor = $ctx->professorActual();

        $turmas = Turma::with('classe.nivelEnsino', 'curso', 'turno')->where('ano_lectivo_id', $ano?->id)->where('activo', true)
            ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))->get()
            ->sortBy(fn ($t) => sprintf('%03d-%03d-%s', $t->classe->nivelEnsino->ordem, $t->classe->ordem, $t->nome));

        // Professores: apenas as disciplinas que leccionam. Restantes perfis: todas as do plano.
        $linhas = [];
        foreach ($turmas as $turma) {
            $plano = $turma->planoCurricular();
            $disciplinas = $plano?->disciplinas ?? collect();
            if ($professor) {
                $minhas = AtribuicaoProfessor::where('turma_id', $turma->id)->where('professor_id', $professor->id)->pluck('disciplina_id');
                $disciplinas = $disciplinas->whereIn('id', $minhas);
            }
            $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id);
            foreach ($disciplinas as $d) {
                $linhas[] = ['turma' => $turma, 'disciplina' => $d, 'configurada' => (bool) $config];
            }
        }

        // Progresso: notas lançadas por turma/disciplina no período actual
        $periodo = (int) $request->input('periodo', $this->periodoActual($ano));
        $contagens = Avaliacao::where('periodo', $periodo)->whereIn('turma_id', $turmas->pluck('id'))
            ->withCount('notas')->get()->groupBy(fn ($a) => $a->turma_id.'-'.$a->disciplina_id)
            ->map(fn ($g) => $g->sum('notas_count'));

        return view('avaliacao.lancamento.index', [
            'ano' => $ano,
            'linhas' => $linhas,
            'periodo' => $periodo,
            'contagens' => $contagens,
            'periodos' => $this->estadoPeriodos($ctx->escolaActivaId(), $ano?->id),
        ]);
    }

    public function grelha(Request $request, Turma $turma, int $disciplina, ContextoEscola $ctx): View
    {
        $disciplina = Disciplina::withoutGlobalScope('escola')->findOrFail($disciplina);
        $this->exigirAcesso($turma, $disciplina->id);
        $periodo = $this->periodo($request);
        $turma->load('classe.nivelEnsino', 'curso', 'anoLectivo');

        $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id, $disciplina->id);
        if (! $config) {
            return view('avaliacao.lancamento.sem-configuracao', compact('turma', 'disciplina'));
        }
        $config->load('escala.intervalos', 'componentes', 'formulas');
        $componentes = $config->componentes->filter(fn (ComponenteAvaliacao $c) => $c->aplicaAoPeriodo($periodo))->values();
        $matriculas = $turma->matriculasActivas()->with('aluno')->get()->sortBy('aluno.nome')->values();
        $colunas = $this->colunas($turma, $disciplina->id, $periodo, $componentes);

        $notas = Nota::whereIn('avaliacao_id', $colunas->pluck('id')->filter())->whereIn('matricula_id', $matriculas->pluck('id'))->get()
            ->groupBy('matricula_id')->map(fn ($g) => $g->pluck('valor', 'avaliacao_id'));

        $resultados = ResultadoAvaliacao::whereIn('matricula_id', $matriculas->pluck('id'))->where('disciplina_id', $disciplina->id)
            ->whereIn('periodo', [$periodo === 4 ? 0 : $periodo, 0])->get()->groupBy('matricula_id');

        $fechado = PeriodoLancamento::estaFechado($turma->escola_id, $turma->ano_lectivo_id, $periodo);

        return view('avaliacao.lancamento.grelha', [
            'turma' => $turma,
            'disciplina' => $disciplina,
            'periodo' => $periodo,
            'config' => $config,
            'componentes' => $componentes,
            'colunas' => $colunas,
            'matriculas' => $matriculas,
            'notas' => $notas,
            'resultados' => $resultados,
            'fechado' => $fechado,
            'podeLancar' => $this->podeLancar($turma, $disciplina->id) && ! $fechado,
            'periodos' => $this->estadoPeriodos($turma->escola_id, $turma->ano_lectivo_id),
        ]);
    }

    public function guardar(Request $request, Turma $turma, int $disciplina): RedirectResponse
    {
        $disciplina = Disciplina::withoutGlobalScope('escola')->findOrFail($disciplina);
        $this->exigirAcesso($turma, $disciplina->id);
        abort_unless($this->podeLancar($turma, $disciplina->id), 403, 'Não tem permissão para lançar avaliações nesta disciplina.');
        $periodo = $this->periodo($request);

        if (PeriodoLancamento::estaFechado($turma->escola_id, $turma->ano_lectivo_id, $periodo)) {
            return back()->with('erro', 'O período está fechado. As notas só podem ser alteradas após reabertura autorizada.');
        }

        $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id, $disciplina->id);
        abort_unless($config, 422, 'Sem configuração de avaliação activa.');
        $componentes = $config->componentes()->get()->filter(fn ($c) => $c->aplicaAoPeriodo($periodo))->keyBy('codigo');
        $matriculas = $turma->matriculasActivas()->pluck('id')->all();

        $dados = $request->input('notas', []);
        if (! is_array($dados)) {
            throw ValidationException::withMessages(['notas' => 'Dados inválidos.']);
        }

        // Validação de todos os valores antes de gravar (tudo ou nada).
        $erros = [];
        $limpos = [];
        foreach ($dados as $matriculaId => $porComponente) {
            if (! in_array((int) $matriculaId, $matriculas, true) || ! is_array($porComponente)) {
                $erros['notas'] = 'Foram enviadas notas de alunos que não pertencem à turma.';

                continue;
            }
            foreach ($porComponente as $codigo => $porIndice) {
                $c = $componentes[$codigo] ?? null;
                if (! $c || ! is_array($porIndice)) {
                    $erros['notas'] = "Componente {$codigo} não se aplica a este período.";

                    continue;
                }
                foreach ($porIndice as $indice => $valor) {
                    $indice = (int) $indice;
                    if ($indice < 1 || $indice > 20 || ($c->tipo === 'valor' && $indice !== 1)) {
                        $erros['notas'] = 'Coluna de avaliação inválida.';

                        continue;
                    }
                    $valor = is_string($valor) ? str_replace(',', '.', trim($valor)) : $valor;
                    if ($valor === '' || $valor === null) {
                        $limpos[] = [(int) $matriculaId, $codigo, $indice, null];

                        continue;
                    }
                    if (! is_numeric($valor) || $valor < $c->nota_minima || $valor > $c->nota_maxima) {
                        $erros["notas.$matriculaId.$codigo.$indice"] = "Valor inválido ({$valor}) em {$codigo}{$indice}: deve estar entre "
                            .rtrim(rtrim(number_format($c->nota_minima, 2, ',', ''), '0'), ',').' e '.rtrim(rtrim(number_format($c->nota_maxima, 2, ',', ''), '0'), ',').'.';

                        continue;
                    }
                    $limpos[] = [(int) $matriculaId, $codigo, $indice, round((float) $valor, 2)];
                }
            }
        }
        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        $alteradas = 0;
        DB::transaction(function () use ($limpos, $turma, $disciplina, $periodo, $request, &$alteradas) {
            $colunas = [];
            foreach ($limpos as [$matriculaId, $codigo, $indice, $valor]) {
                $chave = "$codigo:$indice";
                $colunas[$chave] ??= Avaliacao::firstOrCreate(
                    ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'periodo' => $periodo, 'componente' => $codigo, 'indice' => $indice]
                );
                $existente = Nota::where('avaliacao_id', $colunas[$chave]->id)->where('matricula_id', $matriculaId)->first();
                if ($valor === null) {
                    if ($existente) {
                        $existente->delete();
                        $alteradas++;
                    }
                } elseif (! $existente) {
                    Nota::create(['avaliacao_id' => $colunas[$chave]->id, 'matricula_id' => $matriculaId, 'valor' => $valor, 'lancado_por' => $request->user()->id]);
                    $alteradas++;
                } elseif (abs($existente->valor - $valor) > 1e-9) {
                    $existente->update(['valor' => $valor, 'lancado_por' => $request->user()->id]);
                    $alteradas++;
                }
            }
        });

        // Recálculo dos resultados da disciplina para os alunos da turma.
        foreach (Matricula::with('classe')->whereIn('id', $matriculas)->get() as $m) {
            $this->resultados->recalcularDisciplina($m, $disciplina->id, $request->user()->id);
        }

        Auditoria::registar('actualizar', "Lançou avaliações: {$turma->designacao} · {$disciplina->nome} · ".PeriodoLancamento::PERIODOS[$periodo]
            ." ({$alteradas} alteração(ões))", $turma, ['disciplina_id' => $disciplina->id, 'periodo' => $periodo, 'alteracoes' => $alteradas]);

        return redirect()->route('avaliacao.lancamento.grelha', [$turma, $disciplina->id, 'periodo' => $periodo])
            ->with('sucesso', $alteradas ? "{$alteradas} nota(s) gravada(s). Médias recalculadas." : 'Sem alterações.');
    }

    /** Acrescenta uma coluna de avaliação contínua (ex.: AC4 — Trabalho de grupo). */
    public function adicionarColuna(Request $request, Turma $turma, int $disciplina): RedirectResponse
    {
        $this->exigirAcesso($turma, $disciplina);
        abort_unless($this->podeLancar($turma, $disciplina), 403);
        $periodo = $this->periodo($request);
        $dados = $request->validate([
            'componente' => ['required', 'string', 'max:20'],
            'descricao' => ['nullable', 'string', 'max:100'],
            'data' => ['nullable', 'date'],
        ]);
        abort_if(PeriodoLancamento::estaFechado($turma->escola_id, $turma->ano_lectivo_id, $periodo), 422, 'Período fechado.');

        $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id, $disciplina);
        $componente = $config?->componentes()->where('codigo', $dados['componente'])->where('tipo', 'lista')->first();
        abort_unless($componente && $componente->aplicaAoPeriodo($periodo), 422, 'Componente inválido.');

        $existentes = $this->colunas($turma, $disciplina, $periodo, collect([$componente]));
        $indice = $existentes->max('indice') + 1;
        abort_if($indice > 20, 422, 'Limite de colunas atingido.');
        Avaliacao::create(['turma_id' => $turma->id, 'disciplina_id' => $disciplina, 'periodo' => $periodo,
            'componente' => $componente->codigo, 'indice' => $indice, 'descricao' => $dados['descricao'] ?? null, 'data' => $dados['data'] ?? null]);

        return back()->with('sucesso', "Coluna {$componente->codigo}{$indice} acrescentada.");
    }

    /**
     * Colunas da grelha: avaliações existentes + colunas previstas ainda não criadas
     * (as colunas só são gravadas quando recebem a primeira nota).
     */
    private function colunas(Turma $turma, int $disciplinaId, int $periodo, Collection $componentes): Collection
    {
        $existentes = Avaliacao::where('turma_id', $turma->id)->where('disciplina_id', $disciplinaId)->where('periodo', $periodo)
            ->get()->keyBy(fn ($a) => $a->componente.':'.$a->indice);
        $colunas = collect();
        foreach ($componentes as $c) {
            $maximo = $c->tipo === 'lista' ? max($c->itens_previstos, (int) $existentes->where('componente', $c->codigo)->max('indice')) : 1;
            for ($i = 1; $i <= $maximo; $i++) {
                $a = $existentes[$c->codigo.':'.$i] ?? null;
                $colunas->push((object) [
                    'id' => $a?->id, 'componente' => $c->codigo, 'indice' => $i, 'tipo' => $c->tipo,
                    'rotulo' => $c->codigo.($c->tipo === 'lista' ? $i : ''), 'descricao' => $a?->descricao ?? ($c->tipo === 'lista' ? $c->nome.' '.$i : $c->nome),
                    'min' => $c->nota_minima, 'max' => $c->nota_maxima,
                ]);
            }
        }

        return $colunas;
    }

    private function periodo(Request $request): int
    {
        $p = (int) $request->input('periodo', 1);
        abort_unless(in_array($p, [1, 2, 3, 4], true), 404);

        return $p;
    }

    public static function periodoActual(?AnoLectivo $ano): int
    {
        if (! $ano) {
            return 1;
        }
        foreach ($ano->trimestres as $t) {
            if ($t->data_fim && now()->lte($t->data_fim)) {
                return $t->numero;
            }
        }

        return 3;
    }

    /** Professores: só as turmas/disciplinas atribuídas. Restantes: âmbito da escola. */
    private function exigirAcesso(Turma $turma, int $disciplinaId): void
    {
        $this->exigirTurmaPermitida($turma->id);
        $professor = app(ContextoEscola::class)->professorActual();
        if (app(ContextoEscola::class)->restritoAsAtribuicoes()) {
            abort_unless($professor && AtribuicaoProfessor::where('turma_id', $turma->id)->where('disciplina_id', $disciplinaId)
                ->where('professor_id', $professor->id)->exists(), 404);
        }
    }

    private function podeLancar(Turma $turma, int $disciplinaId): bool
    {
        return auth()->user()->can('notas.lancar') && app(ContextoEscola::class)->podeEscreverDadosOperacionais();
    }

    private function estadoPeriodos(?int $escolaId, ?int $anoId): Collection
    {
        $registos = PeriodoLancamento::withoutGlobalScope('escola')->where('escola_id', $escolaId)->where('ano_lectivo_id', $anoId)->get()->keyBy('periodo');

        return collect(PeriodoLancamento::PERIODOS)->map(fn ($rotulo, $p) => (object) [
            'periodo' => $p, 'rotulo' => $rotulo, 'estado' => $registos[$p]->estado ?? 'aberto',
        ]);
    }
}
