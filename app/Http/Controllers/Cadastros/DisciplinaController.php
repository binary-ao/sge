<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Academico\AutorizaConfiguracao;
use App\Http\Controllers\Controller;
use App\Models\AtribuicaoProfessor;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\NivelEnsino;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Disciplinas: referência provincial (escola_id null) ou próprias de uma escola.
 *  - consulta: referências + disciplinas da escola activa (o professor vê só as que lecciona);
 *  - escrita: referências exigem "academico.global"; as da escola exigem "academico.configurar"
 *    na escola activa (regras em AutorizaConfiguracao).
 * As classes a que uma disciplina se aplica são definidas nos planos curriculares.
 */
class DisciplinaController extends Controller
{
    use AutorizaConfiguracao;

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $disciplinas = $this->consulta($ctx)
            ->with(['escola:id,nome', 'niveisEnsino:id,nome,codigo'])
            ->withCount('planos as classes_count')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('disciplinas.nome', 'like', '%'.$request->q.'%')->orWhere('disciplinas.sigla', 'like', '%'.$request->q.'%')))
            ->when($request->filled('componente'), fn ($q) => $q->where('componente', $request->componente))
            ->when($request->filled('nivel_id'), fn ($q) => $q->whereHas('niveisEnsino', fn ($n) => $n->where('niveis_ensino.id', $request->integer('nivel_id'))))
            ->when($request->filled('classe_id'), fn ($q) => $q->whereHas('planos', fn ($p) => $p->where('classe_id', $request->integer('classe_id'))))
            ->when($request->input('ambito') === 'referencia', fn ($q) => $q->whereNull('disciplinas.escola_id'))
            ->when($request->input('ambito') === 'escola', fn ($q) => $q->whereNotNull('disciplinas.escola_id'))
            ->when($request->filled('estado'), fn ($q) => $q->where('disciplinas.activo', $request->estado === 'activo'))
            ->orderByRaw('disciplinas.escola_id IS NOT NULL')->orderBy('disciplinas.nome')
            ->paginate(20)->withQueryString();

        return view('cadastros.disciplinas.index', [
            'disciplinas' => $disciplinas,
            'niveis' => NivelEnsino::ordenados()->get(['id', 'nome', 'activo']),
            'classes' => Classe::with('nivelEnsino:id,nome')->where('activo', true)->ordenadas()->get(),
            'escolaActiva' => $ctx->escolaActiva(),
            'controlador' => $this,
            'restrito' => $ctx->restritoAsAtribuicoes(),
        ]);
    }

    public function show(int $id, ContextoEscola $ctx): View
    {
        $disciplina = $this->consulta($ctx)->with('escola', 'niveisEnsino')->findOrFail($id);

        $planos = $disciplina->planos()->withoutGlobalScope('escola')
            ->daEscolaOuGlobal($ctx->escolaActivaId())
            ->with('classe.nivelEnsino', 'curso', 'anoLectivo', 'escola')
            ->get()->sortBy(fn ($p) => [$p->classe->nivelEnsino->ordem ?? 0, $p->classe->ordem, $p->escola_id ?? 0]);

        // Turmas (no âmbito do utilizador) em que a disciplina é leccionada no ano actual.
        $turmasAno = AtribuicaoProfessor::where('disciplina_id', $disciplina->id)
            ->whereHas('turma', fn ($t) => $t->where('ano_lectivo_id', \App\Models\AnoLectivo::actual()?->id))
            ->count();

        return view('cadastros.disciplinas.show', [
            'disciplina' => $disciplina,
            'planos' => $planos,
            'turmasAno' => $turmasAno,
            'podeEditar' => $this->podeEditar($disciplina->escola_id),
            'niveis' => NivelEnsino::ordenados()->get(['id', 'nome', 'activo']),
            'escolaActiva' => $ctx->escolaActiva(),
            'controlador' => $this,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $escolaId = $this->escolaDestino($request);
        [$dados, $niveis] = $this->validar($request, $escolaId);

        $disciplina = DB::transaction(function () use ($dados, $niveis, $escolaId) {
            $disciplina = new Disciplina($dados + ['activo' => true]);
            $disciplina->escola_id = $escolaId;
            $disciplina->save();
            $disciplina->niveisEnsino()->sync($niveis);

            return $disciplina;
        });

        return redirect()->route('cadastros.disciplinas.show', $disciplina->id)
            ->with('sucesso', 'Disciplina registada'.($escolaId ? ' para a escola.' : ' como referência provincial.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $disciplina = $this->encontrar($id);
        $this->exigirEdicao($disciplina->escola_id);
        [$dados, $niveis] = $this->validar($request, $disciplina->escola_id, $disciplina);

        DB::transaction(function () use ($disciplina, $dados, $niveis) {
            $disciplina->update($dados);
            $alteracoes = $disciplina->niveisEnsino()->sync($niveis);
            if (array_filter($alteracoes)) {
                Auditoria::registar('actualizar', 'Alterou os níveis de ensino da disciplina: '.$disciplina->nome, $disciplina,
                    ['niveis' => NivelEnsino::whereIn('id', $niveis)->pluck('nome')->all()]);
            }
        });

        return back()->with('sucesso', 'Disciplina actualizada.');
    }

    /** Desactivação lógica: a disciplina mantém-se nas notas, pautas e planos já existentes. */
    public function alternarEstado(int $id): RedirectResponse
    {
        $disciplina = $this->encontrar($id);
        $this->exigirEdicao($disciplina->escola_id);
        $disciplina->update(['activo' => ! $disciplina->activo]);
        Auditoria::registar($disciplina->activo ? 'activar' : 'desactivar',
            ($disciplina->activo ? 'Activou' : 'Desactivou').' a disciplina '.$disciplina->nome, $disciplina);

        return back()->with('sucesso', $disciplina->nome.' '.($disciplina->activo
            ? 'activada.'
            : 'desactivada. Deixa de estar disponível em novos planos; os registos existentes mantêm-se.'));
    }

    /** Disciplinas que o utilizador pode consultar. */
    private function consulta(ContextoEscola $ctx): Builder
    {
        $q = Disciplina::withoutGlobalScope('escola')->daEscolaOuGlobal($ctx->escolaActivaId())->select('disciplinas.*');

        // Professor: apenas as disciplinas que lecciona na escola activa.
        if ($ctx->restritoAsAtribuicoes()) {
            $professor = $ctx->professorActual();
            $q->whereIn('disciplinas.id', $professor
                ? AtribuicaoProfessor::where('professor_id', $professor->id)->pluck('disciplina_id')
                : [0]);
        }

        return $q;
    }

    private function encontrar(int $id): Disciplina
    {
        return Disciplina::withoutGlobalScope('escola')->daEscolaOuGlobal(app(ContextoEscola::class)->escolaActivaId())->findOrFail($id);
    }

    /** @return array{0: array, 1: array<int>} */
    private function validar(Request $request, ?int $escolaId, ?Disciplina $disciplina = null): array
    {
        $request->merge(['sigla' => strtoupper(trim((string) $request->sigla))]);
        $unico = fn (string $campo) => Rule::unique('disciplinas', $campo)->ignore($disciplina)
            ->where(fn ($q) => $escolaId ? $q->where('escola_id', $escolaId) : $q->whereNull('escola_id'));

        $dados = validator($request->all(), [
            'nome' => ['required', 'string', 'max:150', $unico('nome')],
            'sigla' => ['required', 'string', 'max:15', 'regex:/^[A-Z0-9\-]+$/', $unico('sigla')],
            'componente' => ['required', Rule::in(array_keys(Disciplina::COMPONENTES))],
            'nuclear' => ['nullable', 'boolean'],
            'niveis' => ['required', 'array', 'min:1'],
            'niveis.*' => ['integer', 'distinct', Rule::exists('niveis_ensino', 'id')->where(fn ($q) => $q
                ->where('activo', true)
                ->when($disciplina, fn ($w) => $w->orWhereIn('id', $disciplina->niveisEnsino()->pluck('niveis_ensino.id'))))],
        ], [
            'nome.unique' => 'Já existe uma disciplina com este nome neste âmbito.',
            'sigla.unique' => 'Já existe uma disciplina com este código neste âmbito.',
            'sigla.regex' => 'O código só pode conter letras, números e hífenes (ex.: MAT, L-PORT).',
            'niveis.required' => 'Indique pelo menos um nível de ensino em que a disciplina é leccionada.',
        ], ['sigla' => 'código'])->after(function (Validator $v) use ($request, $escolaId) {
            // Evita duplicar, numa escola, uma disciplina de referência provincial do mesmo nível.
            if (! $escolaId || $v->errors()->isNotEmpty()) {
                return;
            }
            $referencia = Disciplina::withoutGlobalScope('escola')->whereNull('escola_id')->where('activo', true)
                ->where(fn ($q) => $q->where('nome', $request->nome)->orWhere('sigla', $request->sigla))
                ->whereHas('niveisEnsino', fn ($n) => $n->whereIn('niveis_ensino.id', (array) $request->input('niveis', [])))
                ->first();
            if ($referencia) {
                $v->errors()->add('nome', "Já existe a disciplina de referência provincial \"{$referencia->nome}\" ({$referencia->sigla}) para este nível. Utilize-a no plano curricular da escola.");
            }
        })->validate();

        return [[
            'nome' => $dados['nome'],
            'sigla' => $dados['sigla'],
            'componente' => $dados['componente'],
            'nuclear' => $request->boolean('nuclear'),
        ], array_map('intval', $dados['niveis'])];
    }
}
