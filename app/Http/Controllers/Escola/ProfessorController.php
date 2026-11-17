<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Perfil;
use App\Models\Professor;
use App\Models\User;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class ProfessorController extends Controller
{
    public function index(Request $request): View
    {
        $ano = AnoLectivo::actual();

        $professores = Professor::query()
            ->withCount(['atribuicoes as total_atribuicoes' => fn ($q) => $q->whereHas('turma', fn ($t) => $t->where('ano_lectivo_id', $ano?->id))])
            ->with('utilizador')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')
                ->orWhere('numero_identificacao', 'like', '%'.$request->q.'%')
                ->orWhere('especialidade', 'like', '%'.$request->q.'%')))
            ->when($request->filled('situacao'), fn ($q) => $q->where('situacao_profissional', $request->situacao))
            ->when($request->filled('genero'), fn ($q) => $q->where('genero', $request->genero))
            ->when($request->input('estado', 'activo') !== 'todos', fn ($q) => $q->where('activo', $request->input('estado', 'activo') === 'activo'))
            ->orderBy('nome')->paginate(20)->withQueryString();

        return view('escola.professores.index', compact('professores'));
    }

    public function show(Professor $professor): View
    {
        $ano = AnoLectivo::actual();
        $professor->load(['utilizador', 'atribuicoes' => fn ($q) => $q->with('turma.classe', 'turma.curso', 'disciplina')
            ->whereHas('turma', fn ($t) => $t->where('ano_lectivo_id', $ano?->id))]);

        return view('escola.professores.show', [
            'professor' => $professor,
            'ano' => $ano,
            'turmasDirigidas' => $professor->hasMany(\App\Models\Turma::class, 'director_turma_id')->where('ano_lectivo_id', $ano?->id)->with('classe', 'curso')->get(),
        ]);
    }

    public function create(ContextoEscola $ctx): View
    {
        return view('escola.professores.form', $this->dadosFormulario(new Professor(['activo' => true, 'genero' => 'M']), $ctx));
    }

    public function store(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        $professor = Professor::create($this->validar($request, $ctx));

        return redirect()->route('professores.show', $professor)->with('sucesso', 'Professor registado.');
    }

    public function edit(Professor $professor, ContextoEscola $ctx): View
    {
        return view('escola.professores.form', $this->dadosFormulario($professor, $ctx));
    }

    public function update(Request $request, Professor $professor, ContextoEscola $ctx): RedirectResponse
    {
        $professor->update($this->validar($request, $ctx, $professor));

        return redirect()->route('professores.show', $professor)->with('sucesso', 'Ficha do professor actualizada.');
    }

    public function alternarEstado(Professor $professor): RedirectResponse
    {
        $professor->update(['activo' => ! $professor->activo]);

        return back()->with('sucesso', $professor->nome.' '.($professor->activo ? 'reactivado' : 'marcado como inactivo').'.');
    }

    private function dadosFormulario(Professor $professor, ContextoEscola $ctx): array
    {
        return [
            'professor' => $professor,
            'contas' => $this->contasDisponiveis($ctx, $professor)->get(),
        ];
    }

    /** Contas com perfil Professor atribuídas a esta escola e ainda não ligadas a outra ficha. */
    private function contasDisponiveis(ContextoEscola $ctx, ?Professor $professor = null)
    {
        $escolaId = $ctx->escolaActivaId();

        return User::query()
            ->whereHas('perfil', fn ($q) => $q->where('codigo', Perfil::PROFESSOR))
            ->whereHas('escolas', fn ($q) => $q->where('escolas.id', $escolaId))
            ->whereNotIn('id', Professor::whereNotNull('utilizador_id')
                ->when($professor?->exists, fn ($q) => $q->whereKeyNot($professor->id))->pluck('utilizador_id'))
            ->orderBy('nome');
    }

    private function validar(Request $request, ContextoEscola $ctx, ?Professor $professor = null): array
    {
        $escolaId = $ctx->escolaActivaId();
        $contas = $this->contasDisponiveis($ctx, $professor)->pluck('id')->all();

        $dados = validator($request->all(), [
            'nome' => ['required', 'string', 'max:150'],
            'genero' => ['required', Rule::in(['M', 'F'])],
            'data_nascimento' => ['nullable', 'date', 'before:-16 years'],
            'numero_identificacao' => ['required', 'string', 'max:30',
                Rule::unique('professores', 'numero_identificacao')->where('escola_id', $escolaId)->ignore($professor)],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'habilitacoes' => ['required', Rule::in(array_keys(Professor::HABILITACOES))],
            'especialidade' => ['nullable', 'string', 'max:150'],
            'situacao_profissional' => ['required', Rule::in(array_keys(Professor::SITUACOES))],
            'data_admissao' => ['nullable', 'date', 'before_or_equal:today'],
            'utilizador_id' => ['nullable', 'integer'],
        ], [
            'data_nascimento.before' => 'O professor deve ter pelo menos 16 anos.',
        ], [
            'numero_identificacao' => 'número de identificação', 'habilitacoes' => 'habilitações académicas',
            'situacao_profissional' => 'situação profissional', 'data_admissao' => 'data de admissão', 'utilizador_id' => 'conta de acesso',
        ])->after(function (Validator $v) use ($request, $contas) {
            if ($request->filled('utilizador_id') && ! in_array($request->integer('utilizador_id'), $contas, true)) {
                $v->errors()->add('utilizador_id', 'A conta seleccionada não está disponível para esta escola.');
            }
        })->validate();

        $dados['numero_identificacao'] = strtoupper($dados['numero_identificacao']);
        $dados['utilizador_id'] = $dados['utilizador_id'] ?? null;

        return $dados;
    }
}
