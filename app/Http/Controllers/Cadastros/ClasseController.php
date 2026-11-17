<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Ciclo;
use App\Models\Classe;
use App\Models\NivelEnsino;
use App\Models\PlanoCurricular;
use App\Models\Turma;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Classes e ciclos (referências provinciais). As disciplinas aplicáveis a cada classe
 * são configuradas nos planos curriculares (referência provincial ou da escola).
 */
class ClasseController extends Controller
{
    public function index(Request $request, ContextoEscola $ctx): View
    {
        $anoId = AnoLectivo::actual()?->id;
        $restritas = $this->classesDoProfessor($ctx);

        $classes = Classe::query()->with('nivelEnsino:id,nome,ordem', 'ciclo:id,nome')
            ->withCount([
                'turmas as turmas_ano' => fn ($q) => $q->where('ano_lectivo_id', $anoId),
            ])
            ->when($restritas !== null, fn ($q) => $q->whereIn('classes.id', $restritas ?: [0]))
            ->when($request->filled('q'), fn ($q) => $q->where('classes.nome', 'like', '%'.$request->q.'%'))
            ->when($request->filled('nivel_id'), fn ($q) => $q->where('classes.nivel_ensino_id', $request->integer('nivel_id')))
            ->when($request->filled('ciclo_id'), fn ($q) => $q->where('classes.ciclo_id', $request->integer('ciclo_id')))
            ->when($request->filled('regime'), fn ($q) => $q->where('classes.regime', $request->regime))
            ->when($request->filled('estado'), fn ($q) => $q->where('classes.activo', $request->estado === 'activo'))
            ->ordenadas()->paginate(20)->withQueryString();

        // Disciplinas do plano aplicável (da escola activa, senão de referência), por classe.
        $planos = PlanoCurricular::withoutGlobalScope('escola')->daEscolaOuGlobal($ctx->escolaActivaId())
            ->whereIn('classe_id', $classes->pluck('id'))->where('activo', true)->whereNull('curso_id')
            ->withCount('disciplinas')->orderByRaw('escola_id IS NULL')->orderByRaw('ano_lectivo_id IS NULL')->get()
            ->unique('classe_id')->keyBy('classe_id');

        return view('cadastros.classes.index', [
            'classes' => $classes,
            'planos' => $planos,
            'niveis' => NivelEnsino::ordenados()->get(),
            'ciclos' => Ciclo::with('nivelEnsino:id,nome')->withCount('classes')->ordenados()->get(),
            'restrito' => $restritas !== null,
        ]);
    }

    public function show(Classe $classe, ContextoEscola $ctx): View
    {
        $restritas = $this->classesDoProfessor($ctx);
        abort_if($restritas !== null && ! in_array($classe->id, $restritas, true), 404);

        $classe->load('nivelEnsino', 'ciclo');
        $planos = PlanoCurricular::withoutGlobalScope('escola')->daEscolaOuGlobal($ctx->escolaActivaId())
            ->where('classe_id', $classe->id)
            ->with(['disciplinas' => fn ($q) => $q->withoutGlobalScope('escola'), 'curso', 'anoLectivo', 'escola'])
            ->orderByRaw('escola_id IS NULL')->orderBy('curso_id')->get();

        // Turmas no âmbito do utilizador (escola activa ou escolas visíveis).
        $turmas = Turma::where('classe_id', $classe->id)->where('ano_lectivo_id', AnoLectivo::actual()?->id)->count();

        return view('cadastros.classes.show', [
            'classe' => $classe,
            'planos' => $planos,
            'turmasAno' => $turmas,
            'niveis' => NivelEnsino::ordenados()->get(),
            'ciclos' => Ciclo::with('nivelEnsino:id,nome')->ordenados()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $classe = Classe::create($this->validarClasse($request) + ['activo' => true]);

        return redirect()->route('cadastros.classes.show', $classe)->with('sucesso', 'Classe registada. Configure agora as disciplinas aplicáveis no plano curricular.');
    }

    public function update(Request $request, Classe $classe): RedirectResponse
    {
        $dados = $this->validarClasse($request, $classe);
        if ($classe->nivel_ensino_id !== (int) $dados['nivel_ensino_id'] && $classe->turmas()->withoutGlobalScope('escola')->exists()) {
            return back()->withInput()->with('erro', 'Não é possível mudar o nível de uma classe que já tem turmas.');
        }
        $classe->update($dados);

        return back()->with('sucesso', 'Classe actualizada.');
    }

    /** Desactivação lógica; bloqueada enquanto houver turmas da classe no ano lectivo actual. */
    public function alternarEstado(Classe $classe): RedirectResponse
    {
        if ($classe->activo) {
            $turmas = $classe->turmas()->withoutGlobalScope('escola')->where('ano_lectivo_id', AnoLectivo::actual()?->id)->count();
            if ($turmas) {
                return back()->with('erro', "Não é possível desactivar a {$classe->nome}: existem {$turmas} turma(s) no ano lectivo actual.");
            }
        } elseif (! $classe->nivelEnsino->activo) {
            return back()->with('erro', 'Active primeiro o nível de ensino '.$classe->nivelEnsino->nome.'.');
        }

        $classe->update(['activo' => ! $classe->activo]);
        Auditoria::registar($classe->activo ? 'activar' : 'desactivar', ($classe->activo ? 'Activou' : 'Desactivou').' a classe '.$classe->nome_completo, $classe);

        return back()->with('sucesso', $classe->nome.' '.($classe->activo ? 'activada.' : 'desactivada. O histórico de turmas, matrículas e notas mantém-se.'));
    }

    public function guardarCiclo(Request $request, ?Ciclo $ciclo = null): RedirectResponse
    {
        $dados = $request->validate([
            'nivel_ensino_id' => ['required', 'integer', Rule::exists('niveis_ensino', 'id')->where(fn ($q) => $q
                ->where('activo', true)->when($ciclo?->exists, fn ($w) => $w->orWhere('id', $ciclo->nivel_ensino_id)))],
            'nome' => ['required', 'string', 'max:100', Rule::unique('ciclos', 'nome')->where('nivel_ensino_id', $request->integer('nivel_ensino_id'))->ignore($ciclo)],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:99'],
        ], ['nome.unique' => 'Já existe um ciclo com este nome neste nível de ensino.']);
        $dados['ordem'] = (int) ($dados['ordem'] ?? 0);

        if ($ciclo?->exists) {
            if ($ciclo->nivel_ensino_id !== (int) $dados['nivel_ensino_id'] && $ciclo->classes()->exists()) {
                return back()->withInput()->with('erro', 'Não é possível mudar o nível de um ciclo que já tem classes associadas.');
            }
            $ciclo->update($dados);
        } else {
            Ciclo::create($dados + ['activo' => true]);
        }

        return back()->with('sucesso', 'Ciclo guardado.');
    }

    public function alternarCiclo(Ciclo $ciclo): RedirectResponse
    {
        if ($ciclo->activo && $ciclo->classes()->where('activo', true)->exists()) {
            return back()->with('erro', "Não é possível desactivar o ciclo \"{$ciclo->nome}\": tem classes activas.");
        }
        $ciclo->update(['activo' => ! $ciclo->activo]);
        Auditoria::registar($ciclo->activo ? 'activar' : 'desactivar', ($ciclo->activo ? 'Activou' : 'Desactivou').' o ciclo '.$ciclo->nome, $ciclo);

        return back()->with('sucesso', 'Ciclo '.$ciclo->nome.' '.($ciclo->activo ? 'activado.' : 'desactivado.'));
    }

    /** Professor: apenas as classes das turmas que lhe estão atribuídas (null = sem restrição). */
    private function classesDoProfessor(ContextoEscola $ctx): ?array
    {
        if (! $ctx->restritoAsAtribuicoes()) {
            return null;
        }
        $professor = $ctx->professorActual();
        if (! $professor) {
            return [];
        }

        return Turma::whereIn('id', AtribuicaoProfessor::where('professor_id', $professor->id)->pluck('turma_id'))
            ->distinct()->pluck('classe_id')->map(fn ($i) => (int) $i)->all();
    }

    private function validarClasse(Request $request, ?Classe $classe = null): array
    {
        $nivelId = $request->integer('nivel_ensino_id');
        $dados = $request->validate([
            'nivel_ensino_id' => ['required', 'integer', Rule::exists('niveis_ensino', 'id')->where(fn ($q) => $q
                ->where('activo', true)->when($classe, fn ($w) => $w->orWhere('id', $classe->nivel_ensino_id)))],
            'ciclo_id' => ['nullable', 'integer', Rule::exists('ciclos', 'id')->where('nivel_ensino_id', $nivelId)],
            'nome' => ['required', 'string', 'max:60', Rule::unique('classes', 'nome')->where('nivel_ensino_id', $nivelId)->ignore($classe)],
            'numero' => ['nullable', 'integer', 'min:0', 'max:20'],
            'regime' => ['required', Rule::in(array_keys(Classe::REGIMES))],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nome.unique' => 'Já existe uma classe com este nome neste nível de ensino.',
            'ciclo_id.exists' => 'O ciclo seleccionado não pertence ao nível de ensino indicado.',
        ]);
        $dados['ordem'] = (int) ($dados['ordem'] ?? $dados['numero'] ?? 0);
        $dados['ciclo_id'] = $dados['ciclo_id'] ?? null;

        return $dados;
    }
}
