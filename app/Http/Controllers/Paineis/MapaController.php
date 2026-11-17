<?php

namespace App\Http\Controllers\Paineis;

use App\Http\Controllers\Controller;
use App\Models\Escola;
use App\Models\Municipio;
use App\Models\TipologiaEscola;
use App\Services\Indicadores\Filtros;
use App\Services\Indicadores\ServicoIndicadores;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mapa geográfico da rede escolar (coordenadas demonstrativas identificadas). */
class MapaController extends Controller
{
    public function index(Request $request, ServicoIndicadores $indicadores): View
    {
        $filtros = Filtros::doPedido($request);
        $alunos = $indicadores->alunosPor($filtros, 'escola')->pluck('total', 'id');
        $tipologias = TipologiaEscola::ordenados()->get();

        $escolas = Escola::with('municipio', 'tipologia', 'niveisEnsino')->whereIn('id', $indicadores->escolas($filtros) ?: [0])->get();
        $pontos = $escolas->filter(fn ($e) => $e->latitude !== null && $e->longitude !== null)->map(fn ($e) => [
            'id' => $e->id,
            'nome' => $e->nome,
            'lat' => $e->latitude,
            'lng' => $e->longitude,
            'municipio' => $e->municipio->nome,
            'tipologia' => $e->tipologia->nome,
            'tipologia_id' => $e->tipologia_id,
            'niveis' => $e->niveisEnsino->pluck('nome')->all(),
            'natureza' => $e->natureza_label,
            'alunos' => (int) ($alunos[$e->id] ?? 0),
            'demo' => $e->coordenadas_demonstrativas,
            'url' => route('escolas.show', $e),
        ])->values();

        return view('paineis.mapa', [
            'filtros' => $filtros,
            'pontos' => $pontos,
            'semCoordenadas' => $escolas->count() - $pontos->count(),
            'municipios' => Municipio::visiveis()->orderBy('nome')->get(),
            'tipologias' => $tipologias,
        ]);
    }
}
