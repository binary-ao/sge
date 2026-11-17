<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Turma;
use App\Models\Turno;
use App\Services\Academico\ServicoMatricula;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MatriculaController extends Controller
{
    use RestringeProfessor;

    public function __construct(private ServicoMatricula $servico) {}

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $anoId = $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id;
        $turmas = $this->turmasPermitidas();

        $matriculas = Matricula::with('aluno', 'classe', 'curso', 'turma', 'turno')
            ->where('ano_lectivo_id', $anoId)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('numero', 'like', '%'.$request->q.'%')
                ->orWhereHas('aluno', fn ($a) => $a->pesquisa($request->q))))
            ->when($request->filled('classe_id'), fn ($q) => $q->where('classe_id', $request->integer('classe_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->input('turma') === 'sem', fn ($q) => $q->whereNull('turma_id')->where('estado', 'activa'))
            ->when($turmas !== null, fn ($q) => $q->whereIn('turma_id', $turmas))
            ->latest('data_matricula')->latest('id')->paginate(25)->withQueryString();

        $base = Matricula::where('ano_lectivo_id', $anoId)->when($turmas !== null, fn ($q) => $q->whereIn('turma_id', $turmas));

        return view('escola.matriculas.index', [
            'matriculas' => $matriculas,
            'anoId' => $anoId,
            'anos' => AnoLectivo::recentes()->get(),
            'classes' => Classe::with('nivelEnsino')->daEscola($ctx->escolaActiva())->ordenadas()->get(),
            'resumo' => [
                'total' => (clone $base)->count(),
                'activas' => (clone $base)->where('estado', 'activa')->count(),
                'semTurma' => (clone $base)->where('estado', 'activa')->whereNull('turma_id')->count(),
                'novas' => (clone $base)->where('tipo', 'nova')->count(),
            ],
        ]);
    }

    public function create(Request $request, ContextoEscola $ctx): View
    {
        $aluno = $request->filled('aluno') ? Aluno::findOrFail($request->integer('aluno')) : null;
        $ano = AnoLectivo::actual();

        return view('escola.matriculas.form', $this->dadosFormulario($ctx, new Matricula([
            'ano_lectivo_id' => $ano?->id,
            'tipo' => 'nova',
            'data_matricula' => now()->toDateString(),
        ]), $aluno));
    }

    public function store(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        $dados = $this->validar($request);
        $aluno = Aluno::findOrFail($dados['aluno_id']);   // global scope: apenas alunos da escola activa

        $matricula = DB::transaction(function () use ($ctx, $aluno, $dados, $request) {
            // Bloqueia a turma para evitar ultrapassar a capacidade em registos simultâneos.
            if (! empty($dados['turma_id'])) {
                Turma::whereKey($dados['turma_id'])->lockForUpdate()->first();
            }

            return $this->servico->criar($ctx->escolaActiva(), $aluno, $dados, $request->user()->id);
        });

        return redirect()->route('alunos.show', $aluno)
            ->with('sucesso', "Matrícula n.º {$matricula->numero} registada".($matricula->turma_id ? ' e aluno colocado na turma.' : '. Falta atribuir a turma.'));
    }

    public function edit(Matricula $matricula, ContextoEscola $ctx): View|RedirectResponse
    {
        if ($matricula->estado !== 'activa') {
            return redirect()->route('alunos.show', $matricula->aluno_id)->with('erro', 'Só é possível editar matrículas activas.');
        }

        return view('escola.matriculas.form', $this->dadosFormulario($ctx, $matricula, $matricula->aluno));
    }

    public function update(Request $request, Matricula $matricula, ContextoEscola $ctx): RedirectResponse
    {
        abort_unless($matricula->estado === 'activa', 422, 'Só é possível editar matrículas activas.');
        $dados = $this->validar($request, $matricula);

        if ($erros = $this->servico->verificar($ctx->escolaActiva(), $matricula->aluno, $dados, $matricula)) {
            throw ValidationException::withMessages($erros);
        }

        $turma = ! empty($dados['turma_id']) ? Turma::find($dados['turma_id']) : null;
        $matricula->update([
            'classe_id' => $dados['classe_id'],
            'curso_id' => $dados['curso_id'] ?? null,
            'turma_id' => $turma?->id,
            'turno_id' => $turma?->turno_id ?? $dados['turno_id'],
            'tipo' => $dados['tipo'],
            'data_matricula' => $dados['data_matricula'],
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        return redirect()->route('alunos.show', $matricula->aluno_id)->with('sucesso', 'Matrícula actualizada.');
    }

    /** Anulação (não se eliminam matrículas): liberta o lugar na turma e regista o motivo. */
    public function anular(Request $request, Matricula $matricula): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo']);
        abort_if($matricula->estado === 'anulada', 422, 'A matrícula já está anulada.');

        $matricula->update(['estado' => 'anulada', 'turma_id' => null,
            'observacoes' => trim(($matricula->observacoes ? $matricula->observacoes."\n" : '').'Anulada: '.$dados['motivo'])]);
        Auditoria::registar('anular', "Anulou a matrícula {$matricula->numero}", $matricula, ['motivo' => $dados['motivo']]);

        return back()->with('sucesso', "Matrícula {$matricula->numero} anulada.");
    }

    private function dadosFormulario(ContextoEscola $ctx, Matricula $matricula, ?Aluno $aluno): array
    {
        $escola = $ctx->escolaActiva();
        $anos = AnoLectivo::whereIn('estado', ['planeado', 'em_curso'])->recentes()->get();

        $turmas = Turma::with('classe', 'curso', 'turno')->whereIn('ano_lectivo_id', $anos->pluck('id'))->where('activo', true)
            ->withCount('matriculasActivas')->get()
            ->map(fn (Turma $t) => [
                'id' => $t->id, 'ano' => $t->ano_lectivo_id, 'classe' => $t->classe_id, 'curso' => $t->curso_id,
                'nome' => $t->designacao.' · '.$t->turno->nome,
                'vagas' => $t->capacidade - $t->matriculas_activas_count + ($matricula->turma_id === $t->id ? 1 : 0),
            ])->sortBy('nome')->values();

        return [
            'matricula' => $matricula,
            'aluno' => $aluno,
            'alunosSemMatricula' => $aluno ? collect() : Aluno::where('estado', 'activo')
                ->whereDoesntHave('matriculas', fn ($m) => $m->where('ano_lectivo_id', $matricula->ano_lectivo_id))
                ->orderBy('nome')->get(['id', 'nome', 'numero_processo']),
            'anos' => $anos,
            'classes' => Classe::with('nivelEnsino')->daEscola($escola)->where('activo', true)->ordenadas()->get(),
            'cursos' => Curso::withoutGlobalScope('escola')->daEscolaOuGlobal($escola->id)->where('activo', true)->get(),
            'turmas' => $turmas,
            'turnos' => Turno::where('activo', true)->orderBy('ordem')->get(),
        ];
    }

    private function validar(Request $request, ?Matricula $matricula = null): array
    {
        $escolaId = app(ContextoEscola::class)->escolaActivaId();

        $regras = [
            'classe_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('activo', true)],
            'curso_id' => ['nullable', 'integer'],
            'turma_id' => ['nullable', 'integer', Rule::exists('turmas', 'id')->where('escola_id', $escolaId)],
            'turno_id' => ['required_without:turma_id', 'nullable', 'integer', 'exists:turnos,id'],
            'tipo' => ['required', Rule::in(array_keys(Matricula::TIPOS))],
            'data_matricula' => ['required', 'date', 'before_or_equal:today'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
        if (! $matricula) {
            $regras['aluno_id'] = ['required', 'integer', Rule::exists('alunos', 'id')->where('escola_id', $escolaId)];
            $regras['ano_lectivo_id'] = ['required', 'integer', Rule::exists('anos_lectivos', 'id')->whereIn('estado', ['planeado', 'em_curso'])];
        }

        $dados = $request->validate($regras, [
            'ano_lectivo_id.exists' => 'Só é possível matricular em anos lectivos planeados ou em curso.',
            'turno_id.required_without' => 'Indique o turno quando a turma ainda não for atribuída.',
        ], ['aluno_id' => 'aluno', 'ano_lectivo_id' => 'ano lectivo', 'classe_id' => 'classe', 'curso_id' => 'curso',
            'turma_id' => 'turma', 'turno_id' => 'turno', 'data_matricula' => 'data da matrícula']);

        if ($matricula) {
            $dados['aluno_id'] = $matricula->aluno_id;
            $dados['ano_lectivo_id'] = $matricula->ano_lectivo_id;
        }
        // O curso só se aplica a níveis que o exigem (II Ciclo, Técnico-Profissional).
        if (! Classe::with('nivelEnsino')->find($dados['classe_id'])?->nivelEnsino?->exige_curso) {
            $dados['curso_id'] = null;
        }

        return $dados;
    }
}
