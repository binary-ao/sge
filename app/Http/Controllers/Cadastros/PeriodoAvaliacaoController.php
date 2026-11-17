<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Escola;
use App\Models\Municipio;
use App\Models\PeriodoLancamento;
use App\Models\Trimestre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Períodos de avaliação (trimestres) de cada ano lectivo: calendário comum a todas as escolas.
 *
 * O calendário (datas) é uma referência provincial ("academico.global"). A abertura e o fecho
 * do lançamento de notas continuam a ser feitos por escola em Avaliação › Fecho de Períodos
 * ("pautas.fechar"); aqui apenas se consulta essa situação.
 * O motor de avaliação usa três trimestres (1–3) e a época de exames (4).
 */
class PeriodoAvaliacaoController extends Controller
{
    public function index(Request $request): View
    {
        $actual = AnoLectivo::actual();
        $anoId = $request->has('ano_lectivo_id') ? $request->integer('ano_lectivo_id') : $actual?->id;

        $trimestres = Trimestre::with('anoLectivo')
            ->join('anos_lectivos', 'anos_lectivos.id', '=', 'trimestres.ano_lectivo_id')
            ->select('trimestres.*')
            ->when($anoId, fn ($q) => $q->where('trimestres.ano_lectivo_id', $anoId))
            ->when($request->filled('numero'), fn ($q) => $q->where('trimestres.numero', $request->integer('numero')))
            ->when($request->filled('situacao'), fn ($q) => $q->naSituacao($request->situacao))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('trimestres.designacao', 'like', '%'.$request->q.'%')->orWhere('anos_lectivos.designacao', 'like', '%'.$request->q.'%')))
            ->orderByDesc('anos_lectivos.data_inicio')->orderBy('trimestres.numero')
            ->paginate(15)->withQueryString();

        // Escolas (no âmbito do utilizador) com o lançamento fechado, por ano e período.
        $fechados = PeriodoLancamento::where('estado', 'fechado')
            ->whereIn('ano_lectivo_id', $trimestres->pluck('ano_lectivo_id')->unique())
            ->selectRaw('ano_lectivo_id, periodo, count(*) as total')->groupBy('ano_lectivo_id', 'periodo')->get()
            ->mapWithKeys(fn ($r) => [$r->ano_lectivo_id.'-'.$r->periodo => (int) $r->total]);

        $anos = AnoLectivo::with('trimestres:id,ano_lectivo_id,numero')->recentes()->get();

        return view('cadastros.periodos.index', [
            'trimestres' => $trimestres,
            'fechados' => $fechados,
            'totalEscolas' => Escola::visiveis()->activas()->count(),
            'anos' => $anos,
            'anoId' => $anoId,
            // Anos com trimestres em falta (para "Novo período").
            'emFalta' => $anos->mapWithKeys(fn ($a) => [$a->id => array_values(array_diff([1, 2, 3], $a->trimestres->pluck('numero')->all()))])
                ->filter(fn ($n) => $n),
        ]);
    }

    /** Situação do lançamento de notas deste período em cada escola visível. */
    public function show(Request $request, Trimestre $trimestre): View
    {
        $trimestre->load('anoLectivo.trimestres');

        $estados = PeriodoLancamento::with('alteradoPor:id,nome')
            ->where('ano_lectivo_id', $trimestre->ano_lectivo_id)->where('periodo', $trimestre->numero)
            ->get()->keyBy('escola_id');
        $fechadas = $estados->where('estado', 'fechado')->keys();

        $escolas = Escola::visiveis()->activas()->with('municipio:id,nome')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')->orWhere('codigo', 'like', '%'.$request->q.'%')))
            ->when($request->filled('municipio_id'), fn ($q) => $q->where('municipio_id', $request->integer('municipio_id')))
            ->when($request->estado === 'fechado', fn ($q) => $q->whereIn('id', $fechadas ?: [0]))
            ->when($request->estado === 'aberto', fn ($q) => $q->whereNotIn('id', $fechadas))
            ->orderBy('nome')->paginate(15)->withQueryString();

        return view('cadastros.periodos.show', [
            'trimestre' => $trimestre,
            'escolas' => $escolas,
            'estados' => $estados,
            'municipios' => Municipio::visiveis()->activos()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /** Cria um trimestre em falta num ano lectivo (os três são criados com o ano). */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'numero' => ['required', 'integer', 'between:1,3',
                Rule::unique('trimestres', 'numero')->where('ano_lectivo_id', $request->integer('ano_lectivo_id'))],
        ], ['numero.unique' => 'Este período já existe no ano lectivo seleccionado.']);

        $trimestre = new Trimestre([
            'ano_lectivo_id' => $request->integer('ano_lectivo_id'),
            'numero' => $request->integer('numero'),
            'designacao' => [1 => 'I', 2 => 'II', 3 => 'III'][$request->integer('numero')].' Trimestre',
        ]);
        $dados = $this->validarDatas($request, $trimestre);
        $trimestre->fill($dados)->save();

        return redirect()->route('cadastros.periodos.index', ['ano_lectivo_id' => $trimestre->ano_lectivo_id])
            ->with('sucesso', $trimestre->designacao.' criado.');
    }

    public function update(Request $request, Trimestre $trimestre): RedirectResponse
    {
        DB::transaction(fn () => $trimestre->update($this->validarDatas($request, $trimestre)));

        return back()->with('sucesso', $trimestre->designacao.' de '.$trimestre->anoLectivo->designacao.' actualizado.');
    }

    /**
     * Datas dentro do ano lectivo, sem sobreposição e pela ordem dos trimestres.
     */
    private function validarDatas(Request $request, Trimestre $trimestre): array
    {
        $ano = AnoLectivo::findOrFail($trimestre->ano_lectivo_id);
        $inicioAno = $ano->data_inicio->toDateString();
        $fimAno = $ano->data_fim->toDateString();

        $dados = validator($request->all(), [
            'designacao' => ['nullable', 'string', 'max:30'],
            'data_inicio' => ['required', 'date', 'after_or_equal:'.$inicioAno, 'before_or_equal:'.$fimAno],
            'data_fim' => ['required', 'date', 'after:data_inicio', 'before_or_equal:'.$fimAno],
        ], [
            'data_inicio.after_or_equal' => 'O período tem de começar dentro do ano lectivo ('.$ano->data_inicio->format('d/m/Y').' a '.$ano->data_fim->format('d/m/Y').').',
            'data_inicio.before_or_equal' => 'O período tem de começar dentro do ano lectivo.',
            'data_fim.before_or_equal' => 'O período tem de terminar dentro do ano lectivo (até '.$ano->data_fim->format('d/m/Y').').',
            'data_fim.after' => 'A data de fim tem de ser posterior à data de início.',
        ])->after(function (Validator $v) use ($request, $trimestre) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $outros = Trimestre::where('ano_lectivo_id', $trimestre->ano_lectivo_id)
                ->when($trimestre->exists, fn ($q) => $q->whereKeyNot($trimestre->id))
                ->whereNotNull('data_inicio')->whereNotNull('data_fim')->get();
            foreach ($outros as $o) {
                if ($o->data_inicio->toDateString() <= $request->data_fim && $o->data_fim->toDateString() >= $request->data_inicio) {
                    $v->errors()->add('data_inicio', "As datas sobrepõem-se ao {$o->designacao} ({$o->data_inicio->format('d/m/Y')} a {$o->data_fim->format('d/m/Y')}).");

                    return;
                }
                if (($o->numero < $trimestre->numero && $o->data_inicio->toDateString() > $request->data_inicio)
                    || ($o->numero > $trimestre->numero && $o->data_inicio->toDateString() < $request->data_inicio)) {
                    $v->errors()->add('data_inicio', "A ordem dos períodos tem de ser respeitada (o {$o->designacao} começa a {$o->data_inicio->format('d/m/Y')}).");

                    return;
                }
            }
        })->validate();
        $dados['designacao'] = $request->filled('designacao') ? $request->designacao : $trimestre->designacao;

        return $dados;
    }
}
