<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Escola\RestringeProfessor;
use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Disciplina;
use App\Models\Falta;
use App\Models\JustificativoFalta;
use App\Models\Matricula;
use App\Models\PeriodoLancamento;
use App\Models\ResultadoFinal;
use App\Models\Turma;
use App\Services\Avaliacao\ServicoResultados;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaltaController extends Controller
{
    use RestringeProfessor;

    public function index(Request $request): View
    {
        $ano = AnoLectivo::actual();
        $permitidas = $this->turmasPermitidas();
        $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date']]);

        $base = Falta::query()
            ->whereHas('matricula', fn ($q) => $q->where('ano_lectivo_id', $ano?->id))
            ->when($permitidas !== null, fn ($q) => $q->whereIn('turma_id', $permitidas))
            ->when($request->filled('turma_id'), fn ($q) => $q->where('turma_id', $request->integer('turma_id')))
            ->when($request->filled('disciplina_id'), fn ($q) => $q->where('disciplina_id', $request->integer('disciplina_id')))
            ->when($request->filled('justificacao'), fn ($q) => $q->where('justificacao', $request->justificacao))
            ->when($request->filled('de'), fn ($q) => $q->whereDate('data', '>=', $request->de))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('data', '<=', $request->ate))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('matricula.aluno', fn ($a) => $a->pesquisa($request->q)));

        $turmas = Turma::with('classe', 'curso')->where('ano_lectivo_id', $ano?->id)
            ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))->get()->sortBy('designacao');

        return view('administrativo.faltas.index', [
            'faltas' => (clone $base)->with('matricula.aluno', 'turma.classe', 'disciplina', 'justificativo')->orderByDesc('data')->orderBy('turma_id')->paginate(30)->withQueryString(),
            'resumo' => [
                'faltas' => (clone $base)->count(),
                'tempos' => (int) (clone $base)->sum('tempos'),
                'pendentes' => (clone $base)->where('justificacao', 'pendente')->count(),
                'justificadas' => (clone $base)->where('justificacao', 'aprovado')->count(),
            ],
            'turmas' => $turmas,
            'disciplinas' => Disciplina::withoutGlobalScope('escola')->whereIn('id', Falta::whereIn('turma_id', $turmas->pluck('id'))->distinct()->pluck('disciplina_id'))->orderBy('nome')->get(),
            'ano' => $ano,
        ]);
    }

    /** Registo de faltas (chamada) de uma turma, disciplina e data. */
    public function chamada(Request $request, ContextoEscola $ctx): View
    {
        $ano = AnoLectivo::actual();
        $permitidas = $this->turmasPermitidas();
        $turmas = Turma::with('classe', 'curso')->where('ano_lectivo_id', $ano?->id)->where('activo', true)
            ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))->get()->sortBy('designacao')->values();

        $turma = $request->filled('turma_id') ? $turmas->firstWhere('id', $request->integer('turma_id')) : null;
        $disciplinas = $turma ? $this->disciplinasPermitidas($turma, $ctx) : collect();
        $disciplina = $turma && $request->filled('disciplina_id') ? $disciplinas->firstWhere('id', $request->integer('disciplina_id')) : null;
        $data = $request->filled('data') ? Carbon::parse($request->input('data')) : now();

        $matriculas = collect();
        $existentes = collect();
        if ($turma && $disciplina) {
            $matriculas = $turma->matriculasActivas()->with('aluno')->get()->sortBy('aluno.nome')->values();
            $existentes = Falta::with('justificativo')->where('turma_id', $turma->id)->where('disciplina_id', $disciplina->id)
                ->whereDate('data', $data)->get()->keyBy('matricula_id');
        }

        return view('administrativo.faltas.chamada', [
            'turmas' => $turmas, 'turma' => $turma, 'disciplinas' => $disciplinas, 'disciplina' => $disciplina,
            'data' => $data, 'matriculas' => $matriculas, 'existentes' => $existentes, 'ano' => $ano,
            'bloqueio' => $turma ? $this->bloqueio($turma, $data) : null,
        ]);
    }

    public function guardarChamada(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        $ano = AnoLectivo::actual();
        $dados = $request->validate([
            'turma_id' => ['required', 'integer'],
            'disciplina_id' => ['required', 'integer'],
            'data' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$ano?->data_inicio->toDateString()],
            'faltas' => ['nullable', 'array'],
            'faltas.*.falta' => ['nullable', 'boolean'],
            'faltas.*.tempos' => ['nullable', 'integer', 'min:1', 'max:8'],
            'faltas.*.motivo' => ['nullable', 'string', 'max:255'],
        ], ['data.before_or_equal' => 'Não é possível registar faltas em datas futuras.', 'data.after_or_equal' => 'A data é anterior ao início do ano lectivo.']);

        $turma = Turma::findOrFail($dados['turma_id']);
        $this->exigirTurmaPermitida($turma->id);
        $disciplina = $this->disciplinasPermitidas($turma, $ctx)->firstWhere('id', (int) $dados['disciplina_id']);
        abort_unless($disciplina, 404);
        $data = Carbon::parse($dados['data']);
        if ($motivo = $this->bloqueio($turma, $data)) {
            return back()->with('erro', $motivo);
        }

        $matriculas = $turma->matriculasActivas()->pluck('id')->all();
        $professorId = $ctx->professorActual()?->id ?? AtribuicaoProfessor::where('turma_id', $turma->id)->where('disciplina_id', $disciplina->id)->value('professor_id');
        $criadas = $removidas = 0;
        $mantidas = [];

        DB::transaction(function () use ($dados, $matriculas, $turma, $disciplina, $data, $professorId, $request, &$criadas, &$removidas, &$mantidas) {
            foreach ($matriculas as $mid) {
                $linha = $dados['faltas'][$mid] ?? [];
                $existente = Falta::where('matricula_id', $mid)->where('disciplina_id', $disciplina->id)->whereDate('data', $data)->first();
                if (! empty($linha['falta'])) {
                    $valores = ['tempos' => (int) ($linha['tempos'] ?? 1), 'motivo' => $linha['motivo'] ?? null];
                    if ($existente) {
                        $existente->update($valores);
                    } else {
                        Falta::create($valores + ['matricula_id' => $mid, 'turma_id' => $turma->id, 'disciplina_id' => $disciplina->id,
                            'professor_id' => $professorId, 'data' => $data->toDateString(), 'registado_por' => $request->user()->id]);
                        $criadas++;
                    }
                } elseif ($existente) {
                    // Uma falta com justificativo submetido não é apagada pela chamada.
                    if ($existente->justificacao !== 'nao_justificada') {
                        $mantidas[] = $existente->matricula_id;
                    } else {
                        $existente->delete();
                        $removidas++;
                    }
                }
            }
        });
        $this->actualizarSituacoesFinais($matriculas);

        $msg = "Chamada guardada: {$criadas} falta(s) registada(s)".($removidas ? ", {$removidas} removida(s)" : '').'.';
        if ($mantidas) {
            $msg .= ' '.count($mantidas).' falta(s) com justificativo foram mantidas.';
        }

        return redirect()->route('faltas.chamada', ['turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'data' => $data->toDateString()])->with('sucesso', $msg);
    }

    /** Submissão de um justificativo (fica pendente de análise). */
    public function justificar(Request $request, Falta $falta): RedirectResponse
    {
        $this->exigirTurmaPermitida($falta->turma_id);
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'max:1000'],
            'documento' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [], ['motivo' => 'motivo', 'documento' => 'documento']);
        if (in_array($falta->justificacao, ['pendente', 'aprovado'], true)) {
            return back()->with('erro', 'Esta falta já tem um justificativo '.($falta->justificacao === 'pendente' ? 'pendente' : 'aprovado').'.');
        }

        $caminho = $nome = null;
        if ($request->hasFile('documento')) {
            $f = $request->file('documento');
            $caminho = $f->storeAs("escolas/{$falta->escola_id}/justificativos", Str::uuid().'.'.$f->extension(), 'local');
            $nome = mb_substr($f->getClientOriginalName(), 0, 200);
        }
        DB::transaction(function () use ($falta, $dados, $caminho, $nome, $request) {
            JustificativoFalta::create(['falta_id' => $falta->id, 'motivo' => $dados['motivo'], 'documento' => $caminho, 'documento_nome' => $nome,
                'estado' => 'pendente', 'submetido_por' => $request->user()->id]);
            $falta->update(['justificacao' => 'pendente']);
        });

        return back()->with('sucesso', 'Justificativo submetido. Aguarda análise da direcção.');
    }

    /** Decisão sobre um justificativo (aprovado / rejeitado). */
    public function analisar(Request $request, JustificativoFalta $justificativo): RedirectResponse
    {
        $dados = $request->validate([
            'estado' => ['required', Rule::in(['aprovado', 'rejeitado'])],
            'observacao' => ['nullable', 'string', 'max:500', 'required_if:estado,rejeitado'],
        ], ['observacao.required_if' => 'Indique o motivo da rejeição.']);
        if ($justificativo->estado !== 'pendente') {
            return back()->with('erro', 'Este justificativo já foi analisado.');
        }

        DB::transaction(function () use ($justificativo, $dados, $request) {
            $justificativo->update(['estado' => $dados['estado'], 'observacao' => $dados['observacao'] ?? null,
                'analisado_por' => $request->user()->id, 'analisado_em' => now()]);
            $justificativo->falta->update(['justificacao' => $dados['estado']]);
        });
        $this->actualizarSituacoesFinais([$justificativo->falta->matricula_id]);

        return back()->with('sucesso', 'Justificativo '.($dados['estado'] === 'aprovado' ? 'aprovado: a falta deixa de contar para a frequência.' : 'rejeitado.'));
    }

    public function documento(JustificativoFalta $justificativo): StreamedResponse
    {
        $this->exigirTurmaPermitida($justificativo->falta->turma_id);
        abort_unless($justificativo->documento && Storage::disk('local')->exists($justificativo->documento), 404);

        return Storage::disk('local')->download($justificativo->documento, $justificativo->documento_nome);
    }

    /* ---------------------------------------------------------------- */

    /** Professores: apenas as disciplinas que leccionam na turma; restantes perfis: plano curricular. */
    private function disciplinasPermitidas(Turma $turma, ContextoEscola $ctx)
    {
        $disciplinas = $turma->planoCurricular()?->disciplinas ?? collect();
        if ($ctx->restritoAsAtribuicoes()) {
            $prof = $ctx->professorActual();
            $minhas = $prof ? AtribuicaoProfessor::where('turma_id', $turma->id)->where('professor_id', $prof->id)->pluck('disciplina_id')->all() : [];
            $disciplinas = $disciplinas->whereIn('id', $minhas)->values();
        }

        return $disciplinas;
    }

    /** As faltas de um trimestre fechado ficam bloqueadas (como as notas). */
    private function bloqueio(Turma $turma, Carbon $data): ?string
    {
        $trimestre = $turma->anoLectivo->trimestres->first(fn ($t) => $t->data_inicio && $t->data_fim && $data->betweenIncluded($t->data_inicio, $t->data_fim));
        if ($trimestre && PeriodoLancamento::estaFechado($turma->escola_id, $turma->ano_lectivo_id, $trimestre->numero)) {
            return "O {$trimestre->designacao} está fechado: as faltas deste período não podem ser alteradas.";
        }

        return null;
    }

    /** A frequência entra na situação final: actualizar os resultados finais já calculados. */
    private function actualizarSituacoesFinais(array $matriculaIds): void
    {
        $servico = app(ServicoResultados::class);
        $comFinal = ResultadoFinal::whereIn('matricula_id', $matriculaIds)->pluck('matricula_id');
        foreach (Matricula::with('classe')->whereIn('id', $comFinal)->get() as $m) {
            $servico->recalcularSituacaoFinal($m);
        }
    }
}
