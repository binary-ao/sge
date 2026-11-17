<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Turno;
use App\Services\Academico\ServicoMatricula;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class TurmaController extends Controller
{
    use RestringeProfessor;

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $anoId = $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id;
        $permitidas = $this->turmasPermitidas();

        $turmas = Turma::with('classe.nivelEnsino', 'curso', 'turno', 'directorTurma')
            ->withCount(['matriculasActivas', 'matriculasActivas as raparigas_count' => fn ($q) => $q->whereHas('aluno', fn ($a) => $a->where('genero', 'F'))])
            ->where('ano_lectivo_id', $anoId)
            ->when($request->filled('classe_id'), fn ($q) => $q->where('classe_id', $request->integer('classe_id')))
            ->when($request->filled('turno_id'), fn ($q) => $q->where('turno_id', $request->integer('turno_id')))
            ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))
            ->get()
            ->sortBy(fn ($t) => sprintf('%03d-%03d-%s-%s', $t->classe->nivelEnsino->ordem, $t->classe->ordem, $t->curso?->codigo, $t->nome))
            ->values();

        return view('escola.turmas.index', [
            'turmas' => $turmas,
            'anoId' => $anoId,
            'anos' => AnoLectivo::recentes()->get(),
            'classes' => Classe::with('nivelEnsino')->daEscola($ctx->escolaActiva())->ordenadas()->get(),
            'turnos' => Turno::orderBy('ordem')->get(),
            'semTurma' => Matricula::where('ano_lectivo_id', $anoId)->where('estado', 'activa')->whereNull('turma_id')->count(),
        ]);
    }

    public function show(Turma $turma): View
    {
        $this->exigirTurmaPermitida($turma->id);
        $turma->load('classe.nivelEnsino', 'curso', 'turno', 'anoLectivo', 'directorTurma');

        $plano = $turma->planoCurricular()?->load('disciplinas');
        $atribuicoes = $turma->atribuicoes()->with('professor')->get()->keyBy('disciplina_id');

        return view('escola.turmas.show', [
            'turma' => $turma,
            'matriculas' => $turma->matriculasActivas()->with('aluno')->get()->sortBy('aluno.nome')->values(),
            'plano' => $plano,
            'atribuicoes' => $atribuicoes,
            'professores' => Professor::activos()->orderBy('nome')->get(['id', 'nome', 'especialidade']),
            'elegiveis' => Matricula::with('aluno')->where('estado', 'activa')->whereNull('turma_id')
                ->where('ano_lectivo_id', $turma->ano_lectivo_id)->where('classe_id', $turma->classe_id)
                ->where(fn ($q) => $turma->curso_id ? $q->where('curso_id', $turma->curso_id) : $q->whereNull('curso_id'))
                ->get()->sortBy('aluno.nome')->values(),
        ]);
    }

    public function create(Request $request, ContextoEscola $ctx): View
    {
        return view('escola.turmas.form', $this->dadosFormulario($ctx, new Turma([
            'ano_lectivo_id' => AnoLectivo::actual()?->id, 'capacidade' => 40, 'activo' => true,
            'classe_id' => $request->integer('classe_id') ?: null,
        ])));
    }

    public function store(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        $turma = Turma::create($this->validar($request, $ctx));

        return redirect()->route('turmas.show', $turma)->with('sucesso', "Turma {$turma->designacao} criada.");
    }

    public function edit(Turma $turma, ContextoEscola $ctx): View
    {
        return view('escola.turmas.form', $this->dadosFormulario($ctx, $turma));
    }

    public function update(Request $request, Turma $turma, ContextoEscola $ctx): RedirectResponse
    {
        $dados = $this->validar($request, $ctx, $turma);
        $ocupados = $turma->matriculasActivas()->count();

        if ($ocupados > 0 && ($dados['classe_id'] != $turma->classe_id || ($dados['curso_id'] ?? null) != $turma->curso_id || $dados['ano_lectivo_id'] != $turma->ano_lectivo_id)) {
            return back()->withInput()->with('erro', 'Não é possível alterar o ano lectivo, a classe ou o curso de uma turma com alunos.');
        }
        if ($dados['capacidade'] < $ocupados) {
            return back()->withInput()->withErrors(['capacidade' => "A turma já tem {$ocupados} alunos."]);
        }

        DB::transaction(function () use ($turma, $dados) {
            $turma->update($dados);
            // O turno dos alunos acompanha o turno da turma.
            $turma->matriculas()->update(['turno_id' => $turma->turno_id]);
        });

        return redirect()->route('turmas.show', $turma)->with('sucesso', 'Turma actualizada.');
    }

    /** Distribuição: coloca matrículas sem turma nesta turma. */
    public function distribuir(Request $request, Turma $turma, ServicoMatricula $servico): RedirectResponse
    {
        $dados = $request->validate([
            'matriculas' => ['required', 'array', 'min:1'],
            'matriculas.*' => ['integer'],
        ], ['matriculas.required' => 'Seleccione pelo menos um aluno.']);

        $colocados = DB::transaction(function () use ($turma, $dados, $servico) {
            Turma::whereKey($turma->id)->lockForUpdate()->first();
            $matriculas = Matricula::whereIn('id', $dados['matriculas'])->whereNull('turma_id')->get();
            foreach ($matriculas as $m) {
                $servico->colocarNaTurma($m, $turma);
            }

            return $matriculas->count();
        });

        return back()->with('sucesso', "{$colocados} aluno(s) colocado(s) na turma {$turma->designacao}.");
    }

    public function retirarAluno(Turma $turma, Matricula $matricula): RedirectResponse
    {
        abort_unless($matricula->turma_id === $turma->id, 404);
        $matricula->update(['turma_id' => null]);

        return back()->with('sucesso', $matricula->aluno->nome.' foi retirado(a) da turma. A matrícula mantém-se activa, sem turma.');
    }

    /** Atribuição de professores às disciplinas do plano curricular da turma. */
    public function atribuirProfessores(Request $request, Turma $turma): RedirectResponse
    {
        $disciplinasPlano = $turma->planoCurricular()?->disciplinas()->pluck('disciplinas.id')->all() ?? [];

        $dados = $request->validate([
            'professores' => ['nullable', 'array'],
            'professores.*' => ['nullable', 'integer', Rule::exists('professores', 'id')->where('escola_id', $turma->escola_id)->where('activo', true)],
            'director_turma_id' => ['nullable', 'integer', Rule::exists('professores', 'id')->where('escola_id', $turma->escola_id)],
        ], [], ['professores.*' => 'professor', 'director_turma_id' => 'director de turma']);

        DB::transaction(function () use ($turma, $dados, $disciplinasPlano) {
            foreach ($disciplinasPlano as $disciplinaId) {
                $professorId = $dados['professores'][$disciplinaId] ?? null;
                $actual = AtribuicaoProfessor::where('turma_id', $turma->id)->where('disciplina_id', $disciplinaId)->first();

                if (! $professorId) {
                    $actual?->delete();
                } elseif (! $actual) {
                    AtribuicaoProfessor::create(['turma_id' => $turma->id, 'disciplina_id' => $disciplinaId, 'professor_id' => $professorId]);
                } elseif ($actual->professor_id !== (int) $professorId) {
                    $actual->update(['professor_id' => $professorId]);
                }
            }
            $turma->update(['director_turma_id' => $dados['director_turma_id'] ?? null]);
        });

        return back()->with('sucesso', 'Atribuições de professores guardadas.');
    }

    private function dadosFormulario(ContextoEscola $ctx, Turma $turma): array
    {
        $escola = $ctx->escolaActiva();

        return [
            'turma' => $turma,
            'anos' => AnoLectivo::whereIn('estado', ['planeado', 'em_curso'])->orWhere('id', $turma->ano_lectivo_id)->recentes()->get(),
            'classes' => Classe::with('nivelEnsino')->daEscola($escola)->where('activo', true)->ordenadas()->get(),
            'cursos' => Curso::withoutGlobalScope('escola')->daEscolaOuGlobal($escola->id)->where('activo', true)->get(),
            'turnos' => Turno::where('activo', true)->orderBy('ordem')->get(),
            'professores' => Professor::activos()->orderBy('nome')->get(['id', 'nome']),
        ];
    }

    private function validar(Request $request, ContextoEscola $ctx, ?Turma $turma = null): array
    {
        $escola = $ctx->escolaActiva();
        $classe = Classe::with('nivelEnsino')->find($request->integer('classe_id'));

        $dados = validator($request->all(), [
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'curso_id' => ['nullable', 'integer'],
            'turno_id' => ['required', 'integer', 'exists:turnos,id'],
            'nome' => ['required', 'string', 'max:30', 'regex:/^[\pL\pN \-]+$/u',
                Rule::unique('turmas', 'nome')->ignore($turma)->where('escola_id', $escola->id)
                    ->where('ano_lectivo_id', $request->integer('ano_lectivo_id'))->where('classe_id', $request->integer('classe_id'))],
            'sala' => ['nullable', 'string', 'max:30'],
            'capacidade' => ['required', 'integer', 'min:1', 'max:120'],
            'director_turma_id' => ['nullable', 'integer', Rule::exists('professores', 'id')->where('escola_id', $escola->id)],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nome.unique' => 'Já existe uma turma com esta designação para a mesma classe e ano lectivo.',
        ], ['ano_lectivo_id' => 'ano lectivo', 'classe_id' => 'classe', 'curso_id' => 'curso', 'turno_id' => 'turno',
            'nome' => 'designação', 'director_turma_id' => 'director de turma'])
        ->after(function (Validator $v) use ($request, $classe, $escola) {
            if ($classe && ! $escola->niveisEnsino()->whereKey($classe->nivel_ensino_id)->exists()) {
                $v->errors()->add('classe_id', 'A escola não ministra o nível de ensino desta classe.');
            }
            if ($classe?->nivelEnsino?->exige_curso && ! $request->filled('curso_id')) {
                $v->errors()->add('curso_id', 'Esta classe exige curso.');
            }
            if ($request->filled('curso_id')) {
                $curso = Curso::withoutGlobalScope('escola')->daEscolaOuGlobal($escola->id)->find($request->integer('curso_id'));
                if (! $curso || $curso->nivel_ensino_id !== $classe?->nivel_ensino_id) {
                    $v->errors()->add('curso_id', 'O curso não corresponde ao nível de ensino da classe.');
                }
            }
        })->validate();

        $dados['nome'] = mb_strtoupper(trim($dados['nome']));
        $dados['curso_id'] = $classe?->nivelEnsino?->exige_curso ? ($dados['curso_id'] ?? null) : null;
        $dados['activo'] = $turma ? $request->boolean('activo') : true;

        return $dados;
    }
}
