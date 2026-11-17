<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\PeriodoLancamento;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Anos lectivos (referência provincial, comum a todas as escolas).
 *
 * Regras:
 *  - existe um único ano lectivo activo ("actual") em toda a província: é o ano por omissão
 *    das matrículas, turmas, avaliações e indicadores. Os anos não se eliminam;
 *  - no máximo um ano pode estar "em curso" ao mesmo tempo;
 *  - as datas do ano têm de conter as datas dos respectivos trimestres.
 */
class AnoLectivoController extends Controller
{
    public function index(Request $request): View
    {
        $anos = AnoLectivo::with('trimestres')->withCount(['turmas', 'matriculas'])
            ->when($request->filled('q'), fn ($q) => $q->where('designacao', 'like', '%'.$request->q.'%'))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->recentes()->paginate(10)->withQueryString();

        return view('cadastros.anos.index', ['anos' => $anos, 'actual' => AnoLectivo::where('actual', true)->first()]);
    }

    public function show(AnoLectivo $ano, ContextoEscola $ctx): View
    {
        $ano->load('trimestres')->loadCount(['turmas', 'matriculas']);

        // Períodos de lançamento fechados por período, nas escolas visíveis ao utilizador.
        $fechados = PeriodoLancamento::where('ano_lectivo_id', $ano->id)->where('estado', 'fechado')
            ->selectRaw('periodo, count(*) as total')->groupBy('periodo')->pluck('total', 'periodo');

        return view('cadastros.anos.show', [
            'ano' => $ano,
            'fechados' => $fechados,
            'totalEscolas' => \App\Models\Escola::visiveis()->activas()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);

        $ano = DB::transaction(function () use ($dados) {
            $ano = AnoLectivo::create($dados + ['actual' => false]);
            // Três trimestres criados automaticamente; as datas são definidas em Períodos de Avaliação.
            foreach ([1 => 'I', 2 => 'II', 3 => 'III'] as $n => $r) {
                $ano->trimestres()->create(['numero' => $n, 'designacao' => "{$r} Trimestre"]);
            }

            return $ano;
        });

        return redirect()->route('cadastros.anos.show', $ano)->with('sucesso', 'Ano lectivo criado com três trimestres. Defina as datas em Períodos de Avaliação.');
    }

    public function update(Request $request, AnoLectivo $ano): RedirectResponse
    {
        $ano->update($this->validar($request, $ano));

        return back()->with('sucesso', 'Ano lectivo actualizado.');
    }

    /** Define o ano lectivo activo (único em toda a província). */
    public function definirActual(AnoLectivo $ano): RedirectResponse
    {
        $outroEmCurso = AnoLectivo::where('estado', 'em_curso')->whereKeyNot($ano->id)->first();
        if ($ano->estado === 'planeado' && $outroEmCurso) {
            return back()->with('erro', "O ano {$outroEmCurso->designacao} ainda está em curso. Encerre-o antes de activar {$ano->designacao}.");
        }

        DB::transaction(function () use ($ano) {
            AnoLectivo::where('actual', true)->lockForUpdate()->update(['actual' => false]);
            $ano->update(['actual' => true, 'estado' => $ano->estado === 'planeado' ? 'em_curso' : $ano->estado]);
        });
        Auditoria::registar('actualizar', 'Definiu o ano lectivo activo: '.$ano->designacao, $ano);

        return back()->with('sucesso', "{$ano->designacao} é agora o ano lectivo activo.");
    }

    private function validar(Request $request, ?AnoLectivo $ano = null): array
    {
        return validator($request->all(), [
            'designacao' => ['required', 'string', 'max:20', 'regex:/^\d{4}(\/\d{4})?$/', Rule::unique('anos_lectivos', 'designacao')->ignore($ano)],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'estado' => ['required', Rule::in(array_keys(AnoLectivo::ESTADOS))],
        ], [
            'designacao.regex' => 'Use o formato 2026/2027 ou 2026.',
            'designacao.unique' => 'Este ano lectivo já está registado.',
        ])->after(function (Validator $v) use ($request, $ano) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if ($request->estado === 'em_curso') {
                $outro = AnoLectivo::where('estado', 'em_curso')->when($ano, fn ($q) => $q->whereKeyNot($ano->id))->first();
                if ($outro) {
                    $v->errors()->add('estado', "Já existe um ano lectivo em curso ({$outro->designacao}). Encerre-o primeiro.");
                }
            }
            if ($ano?->actual && $request->estado === 'planeado') {
                $v->errors()->add('estado', 'O ano lectivo activo não pode voltar ao estado "Planeado".');
            }
            // Sobreposição com outros anos lectivos.
            $sobreposto = AnoLectivo::when($ano, fn ($q) => $q->whereKeyNot($ano->id))
                ->where('data_inicio', '<=', $request->data_fim)->where('data_fim', '>=', $request->data_inicio)->first();
            if ($sobreposto) {
                $v->errors()->add('data_inicio', "As datas sobrepõem-se ao ano lectivo {$sobreposto->designacao}.");
            }
            // As datas dos trimestres já definidas têm de ficar dentro do ano.
            if ($ano) {
                $fora = $ano->trimestres()->where(fn ($q) => $q->where('data_inicio', '<', $request->data_inicio)->orWhere('data_fim', '>', $request->data_fim))->first();
                if ($fora) {
                    $v->errors()->add('data_inicio', "O {$fora->designacao} ficaria fora do ano lectivo. Ajuste primeiro as datas em Períodos de Avaliação.");
                }
            }
        })->validate();
    }
}
