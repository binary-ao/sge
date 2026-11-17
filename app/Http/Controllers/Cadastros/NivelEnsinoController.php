<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Escola;
use App\Models\NivelEnsino;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Níveis de ensino (catálogo provincial). Independentes da tipologia: uma escola pode
 * ministrar vários níveis. Gestão com "rede.catalogos"; consulta com "cadastros.ver".
 */
class NivelEnsinoController extends Controller
{
    public function index(Request $request, ContextoEscola $ctx): View
    {
        $visiveis = $ctx->idsEscolasVisiveis();

        $niveis = NivelEnsino::query()
            ->withCount([
                'ciclos', 'classes',
                'disciplinas' => fn ($q) => $q->whereNull('disciplinas.escola_id'),
                'escolas' => fn ($q) => $q->where('escolas.activo', true)->when($visiveis !== null, fn ($w) => $w->whereIn('escolas.id', $visiveis ?: [0])),
            ])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')->orWhere('codigo', 'like', '%'.$request->q.'%')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activo'))
            ->when($request->filled('curso'), fn ($q) => $q->where('exige_curso', $request->curso === 'sim'))
            ->ordenados()->paginate(15)->withQueryString();

        return view('cadastros.niveis.index', compact('niveis'));
    }

    public function show(NivelEnsino $nivel, ContextoEscola $ctx): View
    {
        $nivel->load(['ciclos' => fn ($q) => $q->withCount('classes'), 'classes.ciclo']);

        $escolas = Escola::visiveis()->activas()
            ->whereHas('niveisEnsino', fn ($q) => $q->where('niveis_ensino.id', $nivel->id))
            ->with('municipio:id,nome', 'tipologia:id,nome')->orderBy('nome')->paginate(10)->withQueryString();

        $disciplinas = $nivel->disciplinas()->withoutGlobalScope('escola')
            ->daEscolaOuGlobal($ctx->escolaActivaId())->orderBy('nome')->get();

        return view('cadastros.niveis.show', compact('nivel', 'escolas', 'disciplinas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $nivel = NivelEnsino::create($this->validar($request) + ['activo' => true]);

        return redirect()->route('cadastros.niveis.show', $nivel)->with('sucesso', 'Nível de ensino registado.');
    }

    public function update(Request $request, NivelEnsino $nivel): RedirectResponse
    {
        $nivel->update($this->validar($request, $nivel));

        return back()->with('sucesso', 'Nível de ensino actualizado.');
    }

    /**
     * Desactivação lógica: as escolas, classes e turmas existentes mantêm-se; o nível deixa
     * de estar disponível em novos cadastros.
     */
    public function alternarEstado(NivelEnsino $nivel): RedirectResponse
    {
        if ($nivel->activo && $nivel->classes()->where('activo', true)->exists()) {
            return back()->with('erro', "Não é possível desactivar \"{$nivel->nome}\": tem classes activas. Desactive primeiro as classes em Classes e Ciclos.");
        }

        $nivel->update(['activo' => ! $nivel->activo]);
        Auditoria::registar($nivel->activo ? 'activar' : 'desactivar',
            ($nivel->activo ? 'Activou' : 'Desactivou').' o nível de ensino '.$nivel->nome, $nivel);

        return back()->with('sucesso', '"'.$nivel->nome.'" '.($nivel->activo ? 'activado.' : 'desactivado. As escolas que o ministram mantêm o registo.'));
    }

    private function validar(Request $request, ?NivelEnsino $nivel = null): array
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->codigo))]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('niveis_ensino', 'nome')->ignore($nivel)],
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('niveis_ensino', 'codigo')->ignore($nivel)],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
            'exige_curso' => ['nullable', 'boolean'],
        ], [
            'nome.unique' => 'Já existe um nível de ensino com este nome.',
            'codigo.unique' => 'Já existe um nível de ensino com este código.',
            'codigo.regex' => 'O código só pode conter letras, números, hífenes e sublinhados.',
        ]);
        $dados['ordem'] = (int) ($dados['ordem'] ?? 0);
        $dados['exige_curso'] = $request->boolean('exige_curso');

        return $dados;
    }
}
