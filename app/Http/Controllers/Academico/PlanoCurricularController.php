<?php

namespace App\Http\Controllers\Academico;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PlanoCurricular;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class PlanoCurricularController extends Controller
{
    use AutorizaConfiguracao;

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $planos = PlanoCurricular::withoutGlobalScope('escola')->daEscolaOuGlobal($ctx->escolaActivaId())
            ->with(['classe.nivelEnsino', 'curso', 'anoLectivo', 'escola'])->withCount('disciplinas')
            ->when($request->filled('classe_id'), fn ($q) => $q->where('classe_id', $request->integer('classe_id')))
            ->join('classes', 'classes.id', '=', 'planos_curriculares.classe_id')
            ->join('niveis_ensino', 'niveis_ensino.id', '=', 'classes.nivel_ensino_id')
            ->orderBy('niveis_ensino.ordem')->orderBy('classes.ordem')->orderByRaw('planos_curriculares.escola_id IS NOT NULL')
            ->select('planos_curriculares.*')->get();

        return view('academico.planos.index', [
            'planos' => $planos,
            'classes' => Classe::with('nivelEnsino')->ordenadas()->get(),
            'escolaActiva' => $ctx->escolaActiva(),
            'controlador' => $this,
        ]);
    }

    public function show(int $id): View
    {
        $plano = $this->encontrar($id)->load('classe.nivelEnsino', 'curso', 'anoLectivo', 'escola', 'disciplinas');

        return view('academico.planos.show', ['plano' => $plano, 'podeEditar' => $this->podeEditar($plano->escola_id)]);
    }

    public function create(Request $request, ContextoEscola $ctx): View
    {
        $plano = new PlanoCurricular(['activo' => true]);

        // Pré-selecção da classe (a partir de Cadastros › Classes e Ciclos).
        if ($request->filled('classe_id') && ($classe = Classe::find($request->integer('classe_id')))) {
            $plano->classe_id = $classe->id;
            $plano->nome = ($ctx->escolaActiva() && $this->podeEditar($ctx->escolaActivaId()) ? 'Plano da escola' : 'Plano de referência').' — '.$classe->nome_completo;
        }

        // Copiar disciplinas de outro plano (ex.: adaptar a referência provincial à escola).
        if ($request->filled('copiar')) {
            $origem = $this->encontrar($request->integer('copiar'))->load('disciplinas');
            $plano->fill($origem->only('classe_id', 'curso_id'));
            $plano->nome = str_replace('Plano de referência', 'Plano da escola', $origem->nome);
            $plano->setRelation('disciplinas', $origem->disciplinas);
        }

        return view('academico.planos.form', $this->dadosFormulario($plano, $ctx));
    }

    public function store(Request $request): RedirectResponse
    {
        $escolaId = $this->escolaDestino($request);
        [$dados, $disciplinas] = $this->validar($request, $escolaId);

        $plano = DB::transaction(function () use ($dados, $disciplinas, $escolaId) {
            $plano = new PlanoCurricular($dados);
            $plano->escola_id = $escolaId;
            $plano->save();
            $plano->disciplinas()->sync($disciplinas);

            return $plano;
        });

        return redirect()->route('academico.planos.show', $plano->id)->with('sucesso', 'Plano curricular criado.');
    }

    public function edit(int $id, ContextoEscola $ctx): View
    {
        $plano = $this->encontrar($id)->load('disciplinas');
        $this->exigirEdicao($plano->escola_id);

        return view('academico.planos.form', $this->dadosFormulario($plano, $ctx));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $plano = $this->encontrar($id);
        $this->exigirEdicao($plano->escola_id);
        [$dados, $disciplinas] = $this->validar($request, $plano->escola_id, $plano);

        DB::transaction(function () use ($plano, $dados, $disciplinas) {
            $plano->update($dados);
            $alteracoes = $plano->disciplinas()->sync($disciplinas);
            if (array_filter($alteracoes)) {
                Auditoria::registar('actualizar', 'Alterou as disciplinas do plano: '.$plano->nome, $plano, ['alteracoes' => $alteracoes]);
            }
        });

        return redirect()->route('academico.planos.show', $plano->id)->with('sucesso', 'Plano curricular actualizado.');
    }

    private function encontrar(int $id): PlanoCurricular
    {
        return PlanoCurricular::withoutGlobalScope('escola')->daEscolaOuGlobal(app(ContextoEscola::class)->escolaActivaId())->findOrFail($id);
    }

    private function dadosFormulario(PlanoCurricular $plano, ContextoEscola $ctx): array
    {
        $escola = $ctx->escolaActiva();
        $escolaId = $escola?->id;

        return [
            'plano' => $plano,
            'escolaActiva' => $escola,
            'controlador' => $this,
            'classes' => Classe::with('nivelEnsino')->where('activo', true)
                ->when($escola && ! $plano->exists, fn ($q) => $q->daEscola($escola))->ordenadas()->get(),
            'cursos' => Curso::withoutGlobalScope('escola')->daEscolaOuGlobal($escolaId)->where('activo', true)->with('nivelEnsino')->orderBy('nome')->get(),
            'anos' => AnoLectivo::recentes()->get(),
            'disciplinas' => Disciplina::withoutGlobalScope('escola')->daEscolaOuGlobal($plano->exists ? $plano->escola_id : $escolaId)
                ->where('activo', true)->with('niveisEnsino:id')->orderBy('nome')->get(),
            'seleccionadas' => collect(old('disciplinas', $plano->relationLoaded('disciplinas')
                ? $plano->disciplinas->mapWithKeys(fn ($d) => [$d->id => [
                    'incluir' => 1, 'carga_horaria' => $d->pivot->carga_horaria, 'especifica' => $d->pivot->especifica,
                    'opcional' => ! $d->pivot->obrigatoria,
                ]])->all() : [])),
        ];
    }

    /** @return array{0: array, 1: array} dados do plano e disciplinas para sync */
    private function validar(Request $request, ?int $escolaId, ?PlanoCurricular $plano = null): array
    {
        $classe = Classe::with('nivelEnsino')->find($request->integer('classe_id'));

        $validado = validator($request->all(), [
            'nome' => ['required', 'string', 'max:150'],
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'curso_id' => ['nullable', 'integer', Rule::exists('cursos', 'id')->where(fn ($q) => $q->whereNull('escola_id')->when($escolaId, fn ($w) => $w->orWhere('escola_id', $escolaId)))],
            'ano_lectivo_id' => ['nullable', 'integer', 'exists:anos_lectivos,id'],
            'activo' => ['nullable', 'boolean'],
            'disciplinas' => ['required', 'array'],
            'disciplinas.*.incluir' => ['nullable', 'boolean'],
            'disciplinas.*.carga_horaria' => ['nullable', 'integer', 'min:0', 'max:40'],
            'disciplinas.*.especifica' => ['nullable', 'boolean'],
            'disciplinas.*.opcional' => ['nullable', 'boolean'],
        ])->after(function (Validator $v) use ($request, $classe, $escolaId, $plano) {
            if ($classe?->nivelEnsino?->exige_curso && ! $request->filled('curso_id')) {
                $v->errors()->add('curso_id', 'Esta classe pertence a um nível que exige curso.');
            }
            if ($request->filled('curso_id') && $classe && Curso::withoutGlobalScope('escola')->whereKey($request->integer('curso_id'))->value('nivel_ensino_id') !== $classe->nivel_ensino_id) {
                $v->errors()->add('curso_id', 'O curso não pertence ao nível de ensino da classe.');
            }
            $incluidas = collect($request->input('disciplinas', []))->filter(fn ($d) => ! empty($d['incluir']));
            if ($incluidas->isEmpty()) {
                $v->errors()->add('disciplinas', 'Seleccione pelo menos uma disciplina.');
            }
            $validas = Disciplina::withoutGlobalScope('escola')->daEscolaOuGlobal($escolaId)->whereIn('id', $incluidas->keys())->count();
            if ($validas !== $incluidas->count()) {
                $v->errors()->add('disciplinas', 'Foram seleccionadas disciplinas inválidas.');
            }
            // Um único plano por escola + ano + classe + curso.
            $duplicado = PlanoCurricular::withoutGlobalScope('escola')
                ->where('classe_id', $request->integer('classe_id'))
                ->where(fn ($q) => $request->filled('curso_id') ? $q->where('curso_id', $request->integer('curso_id')) : $q->whereNull('curso_id'))
                ->where(fn ($q) => $escolaId ? $q->where('escola_id', $escolaId) : $q->whereNull('escola_id'))
                ->where(fn ($q) => $request->filled('ano_lectivo_id') ? $q->where('ano_lectivo_id', $request->integer('ano_lectivo_id')) : $q->whereNull('ano_lectivo_id'))
                ->when($plano, fn ($q) => $q->whereKeyNot($plano->id))->exists();
            if ($duplicado) {
                $v->errors()->add('classe_id', 'Já existe um plano para esta classe, curso e ano lectivo neste âmbito.');
            }
        })->validate();

        $disciplinas = [];
        $ordem = 0;
        foreach ($validado['disciplinas'] as $id => $d) {
            if (! empty($d['incluir'])) {
                $disciplinas[(int) $id] = [
                    'carga_horaria' => $d['carga_horaria'] ?? null,
                    'especifica' => ! empty($d['especifica']),
                    'obrigatoria' => empty($d['opcional']),
                    'ordem' => ++$ordem,
                ];
            }
        }

        return [[
            'nome' => $validado['nome'],
            'classe_id' => (int) $validado['classe_id'],
            'curso_id' => $validado['curso_id'] ?? null,
            'ano_lectivo_id' => $validado['ano_lectivo_id'] ?? null,
            'activo' => $request->boolean('activo'),
        ], $disciplinas];
    }
}
