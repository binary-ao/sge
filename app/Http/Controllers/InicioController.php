<?php

namespace App\Http\Controllers;

use App\Models\Escola;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\TipologiaEscola;
use App\Support\ContextoEscola;
use App\Support\Menu;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Página inicial: resumo da rede escolar dentro do âmbito do utilizador.
 * Todos os valores são calculados a partir da base de dados.
 */
class InicioController extends Controller
{
    public function __invoke(ContextoEscola $contexto): View
    {
        $escolas = Escola::visiveis();

        $porTipologia = TipologiaEscola::query()
            ->select('tipologias_escola.id', 'tipologias_escola.nome')
            ->selectSub(Escola::visiveis()->selectRaw('count(*)')->whereColumn('tipologia_id', 'tipologias_escola.id'), 'total')
            ->ordenados()->get()->where('total', '>', 0)->values();

        $porNatureza = (clone $escolas)->select('natureza', DB::raw('count(*) as total'))
            ->groupBy('natureza')->pluck('total', 'natureza');

        $porNivel = NivelEnsino::query()->ordenados()->get()->map(fn ($n) => [
            'id' => $n->id,
            'nome' => $n->nome,
            'total' => $n->escolas()->visiveis()->count(),
        ])->where('total', '>', 0)->values();

        $porMunicipio = Municipio::visiveis()->orderBy('nome')
            ->withCount(['escolas' => fn ($q) => $q->visiveis()])
            ->get();

        // Totais académicos no âmbito do utilizador (soma das escolas visíveis).
        $ano = \App\Models\AnoLectivo::actual();
        $totais = Escola::visiveis()->comIndicadores($ano?->id)->get(['escolas.id']);

        return view('inicio', [
            'contexto' => $contexto,
            'ano' => $ano,
            'totalAlunos' => $totais->sum('total_alunos'),
            'totalProfessores' => $totais->sum('total_professores'),
            'totalTurmas' => $totais->sum('total_turmas'),
            'escolaActiva' => $contexto->escolaActiva()?->load('niveisEnsino'),
            'totalEscolas' => (clone $escolas)->count(),
            'totalMunicipios' => $porMunicipio->count(),
            'emFuncionamento' => (clone $escolas)->where('estado_funcionamento', 'em_funcionamento')->count(),
            'totalNiveis' => $porNivel->count(),
            'porTipologia' => $porTipologia,
            'porNatureza' => $porNatureza,
            'porNivel' => $porNivel,
            'porMunicipio' => $porMunicipio,
        ]);
    }

    public function emDesenvolvimento(string $modulo): View
    {
        $item = Menu::itemPorSlug($modulo);
        abort_unless($item && ! empty($item['em_desenvolvimento']), 404);
        if (! empty($item['permissao'])) {
            abort_unless(Menu::temAlguma(auth()->user(), $item['permissao']), 403);
        }

        return view('em-desenvolvimento', ['item' => $item]);
    }
}
