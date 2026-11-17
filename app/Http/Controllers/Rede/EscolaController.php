<?php

namespace App\Http\Controllers\Rede;

use App\Http\Controllers\Controller;
use App\Http\Requests\EscolaRequest;
use App\Models\Escola;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\TipologiaEscola;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EscolaController extends Controller
{
    public function index(Request $request): View
    {
        $escolas = Escola::visiveis()
            ->with('municipio', 'tipologia', 'niveisEnsino')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')
                ->orWhere('codigo', 'like', '%'.$request->q.'%')
                ->orWhere('comuna', 'like', '%'.$request->q.'%')))
            ->when($request->filled('municipio_id'), fn ($q) => $q->where('municipio_id', $request->integer('municipio_id')))
            ->when($request->filled('tipologia_id'), fn ($q) => $q->where('tipologia_id', $request->integer('tipologia_id')))
            ->when($request->filled('nivel_id'), fn ($q) => $q->whereHas('niveisEnsino', fn ($n) => $n->where('niveis_ensino.id', $request->integer('nivel_id'))))
            ->when($request->filled('natureza'), fn ($q) => $q->where('natureza', $request->natureza))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado_funcionamento', $request->estado))
            ->when($request->filled('registo'), fn ($q) => $q->where('escolas.activo', $request->registo === 'activo'))
            ->comIndicadores(\App\Models\AnoLectivo::actual()?->id)
            ->orderBy('nome')->paginate(15)->withQueryString();

        return view('rede.escolas.index', [
            'escolas' => $escolas,
            'municipios' => Municipio::visiveis()->orderBy('nome')->get(),
            'tipologias' => TipologiaEscola::ordenados()->get(),
            'niveis' => NivelEnsino::ordenados()->get(),
        ]);
    }

    public function show(Escola $escola, ContextoEscola $contexto): View
    {
        // Isolamento: escolas fora do âmbito não existem para o utilizador.
        abort_unless($contexto->podeVerEscola($escola), 404);

        $escola->load('municipio.provincia', 'tipologia', 'niveisEnsino', 'utilizadores.perfil');
        $ano = \App\Models\AnoLectivo::actual();
        $indicadores = Escola::whereKey($escola->id)->comIndicadores($ano?->id)->first();
        $porGenero = \App\Models\Aluno::semFiltroEscola()->where('escola_id', $escola->id)->where('estado', 'activo')
            ->selectRaw('genero, count(*) as total')->groupBy('genero')->pluck('total', 'genero');
        $matriculasAno = \App\Models\Matricula::semFiltroEscola()->where('escola_id', $escola->id)
            ->where('ano_lectivo_id', $ano?->id)->where('estado', 'activa')->count();

        return view('rede.escolas.show', [
            'escola' => $escola,
            'ano' => $ano,
            'indicadores' => $indicadores,
            'porGenero' => $porGenero,
            'matriculasAno' => $matriculasAno,
            'eActiva' => $contexto->escolaActivaId() === $escola->id,
            'podeEntrar' => $contexto->ambito() !== 'escola' || count($contexto->idsEscolasVisiveis()) > 1,
        ]);
    }

    public function create(): View
    {
        return view('rede.escolas.form', $this->dadosFormulario(new Escola([
            'municipio_id' => request()->integer('municipio_id') ?: (auth()->user()->perfil?->ambito === 'municipio' ? auth()->user()->municipio_id : null),
            'natureza' => 'publica',
            'estado_funcionamento' => 'em_funcionamento',
        ])));
    }

    public function store(EscolaRequest $request): RedirectResponse
    {
        $escola = DB::transaction(function () use ($request) {
            $escola = Escola::create($request->safe()->except('niveis'));
            $escola->niveisEnsino()->sync($request->validated('niveis'));

            return $escola;
        });

        return redirect()->route('escolas.show', $escola)->with('sucesso', 'Escola registada com sucesso.');
    }

    public function edit(Escola $escola, ContextoEscola $contexto): View
    {
        abort_unless($contexto->podeVerEscola($escola), 404);

        return view('rede.escolas.form', $this->dadosFormulario($escola->load('niveisEnsino')));
    }

    public function update(EscolaRequest $request, Escola $escola): RedirectResponse
    {
        DB::transaction(function () use ($request, $escola) {
            $escola->update($request->safe()->except('niveis'));
            $alteracoes = $escola->niveisEnsino()->sync($request->validated('niveis'));
            if (array_filter($alteracoes)) {
                \App\Support\Auditoria::registar('actualizar', 'Alterou os níveis de ensino da escola: '.$escola->nome, $escola,
                    ['niveis' => NivelEnsino::whereIn('id', $request->validated('niveis'))->pluck('nome')->all()]);
            }
        });

        return redirect()->route('escolas.show', $escola)->with('sucesso', 'Dados da escola actualizados.');
    }

    /**
     * Activar/desactivar o registo da escola (desactivação lógica: nada é eliminado).
     * Uma escola desactivada sai dos indicadores e não pode ser escolhida como escola activa.
     */
    public function alternarEstado(Escola $escola, ContextoEscola $contexto): RedirectResponse
    {
        // Jurisdição: fora do âmbito, a escola "não existe" para o utilizador.
        abort_unless($contexto->podeVerEscola($escola), 404);

        if (! $escola->activo && ! $escola->municipio->activo) {
            return back()->with('erro', 'O município '.$escola->municipio->nome.' está desactivado. Active-o primeiro.');
        }
        $escola->update(['activo' => ! $escola->activo]);
        \App\Support\Auditoria::registar($escola->activo ? 'activar' : 'desactivar',
            ($escola->activo ? 'Activou' : 'Desactivou').' a escola '.$escola->nome, $escola);

        if (! $escola->activo && session(ContextoEscola::SESSAO) == $escola->id) {
            $contexto->definirEscolaActiva(null);
        }

        return back()->with('sucesso', $escola->nome.' '.($escola->activo
            ? 'activada.'
            : 'desactivada. O histórico mantém-se, mas deixa de contar nos indicadores e de estar disponível para operações.'));
    }

    private function dadosFormulario(Escola $escola): array
    {
        $activosOuActual = fn ($modelo, $actual) => $modelo::query()
            ->where(fn ($q) => $q->where('activo', true)->when($actual, fn ($w) => $w->orWhere('id', $actual)));

        return [
            'escola' => $escola,
            // Direcção Municipal: apenas o município da sua jurisdição.
            'municipios' => $activosOuActual(Municipio::class, $escola->municipio_id)->visiveis()->orderBy('nome')->get(),
            'tipologias' => $activosOuActual(TipologiaEscola::class, $escola->tipologia_id)->orderBy('ordem')->get(),
            'niveis' => NivelEnsino::where('activo', true)
                ->orWhereIn('id', $escola->exists ? $escola->niveisEnsino->pluck('id') : [])
                ->orderBy('ordem')->get(),
            'niveisSeleccionados' => collect(old('niveis', $escola->exists ? $escola->niveisEnsino->pluck('id')->all() : []))
                ->map(fn ($i) => (int) $i)->all(),
        ];
    }
}
