<?php

namespace App\Http\Controllers\Paineis;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\ResultadoAvaliacao;
use App\Models\TipologiaEscola;
use App\Models\Turma;
use App\Services\Indicadores\Filtros;
use App\Services\Indicadores\ServicoIndicadores;
use App\Support\ContextoEscola;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PainelController extends Controller
{
    public function __construct(private ServicoIndicadores $indicadores) {}

    /** Painel da Direcção Provincial: visão consolidada, com filtros globais. */
    public function provincial(Request $request): View
    {
        $filtros = Filtros::doPedido($request);

        return view('paineis.provincial', $this->dados($filtros) + $this->opcoesFiltro($filtros));
    }

    /** Painel da Direcção Municipal: limitado ao município do utilizador. */
    public function municipal(Request $request, ContextoEscola $ctx): View
    {
        $filtros = Filtros::doPedido($request);
        $municipios = Municipio::visiveis()->orderBy('nome')->get();
        if ($ctx->ambito() === 'municipio') {
            $filtros->municipioId = $request->user()->municipio_id;   // nunca outro município
        } elseif (! $filtros->municipioId || ! $municipios->contains('id', $filtros->municipioId)) {
            $filtros->municipioId = $municipios->first()?->id;
        }
        abort_unless($filtros->municipioId && $municipios->contains('id', $filtros->municipioId), 403);

        return view('paineis.municipal', $this->dados($filtros) + $this->opcoesFiltro($filtros) + [
            'municipio' => $municipios->firstWhere('id', $filtros->municipioId),
            'escolherMunicipio' => $ctx->ambito() !== 'municipio',
        ]);
    }

    /** Painel da escola activa. */
    public function escola(Request $request, ContextoEscola $ctx): View
    {
        $escola = $ctx->escolaActiva();
        $filtros = new Filtros(escolaId: $escola->id, anoLectivoId: $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id);
        $anoDesempenho = $this->indicadores->anoDesempenho($filtros);
        $ano = AnoLectivo::find($filtros->anoLectivoId);

        $matriculas = Matricula::where('ano_lectivo_id', $filtros->anoLectivoId)->whereIn('estado', ['activa', 'concluida']);

        // Desempenho no período mais recente com resultados do ano seleccionado, por disciplina.
        $periodo = (int) ResultadoAvaliacao::whereIn('situacao', ['positiva', 'negativa'])->where('periodo', '>', 0)
            ->whereHas('matricula', fn ($q) => $q->where('ano_lectivo_id', $filtros->anoLectivoId))->max('periodo');
        $porDisciplina = $periodo ? DB::table('resultados_avaliacao as r')->join('matriculas as m', 'm.id', '=', 'r.matricula_id')
            ->join('disciplinas as d', 'd.id', '=', 'r.disciplina_id')
            ->where('r.escola_id', $escola->id)->where('m.ano_lectivo_id', $filtros->anoLectivoId)->where('r.periodo', $periodo)
            ->whereIn('r.situacao', ['positiva', 'negativa'])
            ->selectRaw("d.nome, sum(r.situacao = 'positiva') as positivas, sum(r.situacao = 'negativa') as negativas")
            ->groupBy('d.nome')->orderByRaw("sum(r.situacao = 'negativa') / count(*) desc")->get() : collect();

        // Assiduidade e situação financeira (ano seleccionado)
        $frequencia = app(\App\Services\Administrativo\ServicoFrequencia::class);
        $alerta = (float) config('administrativo.faltas.alerta_frequencia', 75);
        $emRisco = Matricula::with('anoLectivo', 'aluno', 'classe', 'turma')->where('ano_lectivo_id', $filtros->anoLectivoId)->where('estado', 'activa')
            ->whereHas('faltas')->get()
            ->map(fn ($m) => ['matricula' => $m, 'frequencia' => $frequencia->percentagem($m)])
            ->filter(fn ($l) => $l['frequencia'] !== null && $l['frequencia'] < $alerta)->sortBy('frequencia')->values();
        $atraso = $filtros->anoLectivoId ? app(\App\Services\Administrativo\ServicoPagamentos::class)->emAtraso($filtros->anoLectivoId) : collect();

        return view('paineis.escola', [
            'faltas' => [
                'ultimos30' => \App\Models\Falta::where('data', '>=', now()->subDays(30)->toDateString())->count(),
                'pendentes' => \App\Models\Falta::where('justificacao', 'pendente')->count(),
                'emRisco' => $emRisco,
                'alerta' => $alerta,
            ],
            'financeiro' => [
                'mes' => (float) \App\Models\Recibo::where('estado', 'emitido')->whereBetween('data', [now()->startOfMonth()->toDateString(), now()->toDateString()])->sum('total'),
                'recibosMes' => \App\Models\Recibo::where('estado', 'emitido')->whereBetween('data', [now()->startOfMonth()->toDateString(), now()->toDateString()])->count(),
                'atrasoAlunos' => $atraso->count(),
                'atrasoValor' => $atraso->sum(fn ($l) => $l['meses']->sum('valor')),
            ],
            'escola' => $escola->load('niveisEnsino', 'municipio', 'tipologia'),
            'ano' => $ano,
            'anos' => AnoLectivo::recentes()->get(),
            'totais' => $this->indicadores->totais($filtros),
            'matriculasAno' => (clone $matriculas)->count(),
            'novas' => (clone $matriculas)->where('tipo', 'nova')->count(),
            'semTurma' => (clone $matriculas)->where('estado', 'activa')->whereNull('turma_id')->count(),
            'genero' => DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')->where('m.escola_id', $escola->id)
                ->where('m.ano_lectivo_id', $filtros->anoLectivoId)->whereIn('m.estado', ['activa', 'concluida'])
                ->selectRaw('a.genero, count(*) as total')->groupBy('a.genero')->pluck('total', 'genero'),
            'alunosPorClasse' => $this->indicadores->alunosPor($filtros, 'classe'),
            'anoDesempenho' => $anoDesempenho,
            'desempenho' => $this->indicadores->desempenho($filtros, $anoDesempenho),
            'desempenhoNivel' => $this->indicadores->desempenhoPor($filtros, $anoDesempenho, 'nivel'),
            'periodo' => $periodo,
            'porDisciplina' => $porDisciplina,
            'turmas' => Turma::with('classe', 'curso', 'turno')->withCount('matriculasActivas')->where('ano_lectivo_id', $filtros->anoLectivoId)->get()->sortBy('designacao'),
            'comparacao' => $this->indicadores->comparacaoAnos($filtros),
        ]);
    }

    /** Dados comuns aos painéis provincial e municipal. */
    private function dados(Filtros $f): array
    {
        $anoDesempenho = $this->indicadores->anoDesempenho($f);

        return [
            'filtros' => $f,
            'ano' => AnoLectivo::find($f->anoLectivoId),
            'anoDesempenho' => $anoDesempenho,
            'totais' => $this->indicadores->totais($f),
            'desempenho' => $this->indicadores->desempenho($f, $anoDesempenho),
            'escolasTipologia' => $this->indicadores->escolasPor($f, 'tipologia'),
            'escolasMunicipio' => $this->indicadores->escolasPor($f, 'municipio'),
            'escolasNatureza' => $this->indicadores->escolasPor($f, 'natureza'),
            'escolasNivel' => $this->indicadores->escolasPor($f, 'nivel'),
            'alunosNivel' => $this->indicadores->alunosPor($f, 'nivel'),
            'alunosTipologia' => $this->indicadores->alunosPor($f, 'tipologia'),
            'professoresMunicipio' => $this->indicadores->professoresPor($f, 'municipio'),
            'professoresTipologia' => $this->indicadores->professoresPor($f, 'tipologia'),
            'turmasMunicipio' => $this->indicadores->turmasPor($f, 'municipio'),
            'aprovacaoEscola' => $this->indicadores->desempenhoPor($f, $anoDesempenho, 'escola'),
            'aprovacaoMunicipio' => $this->indicadores->desempenhoPor($f, $anoDesempenho, 'municipio'),
            'aprovacaoNivel' => $this->indicadores->desempenhoPor($f, $anoDesempenho, 'nivel'),
            'aprovacaoTipologia' => $this->indicadores->desempenhoPor($f, $anoDesempenho, 'tipologia'),
            'distribuicao' => $this->indicadores->distribuicaoClassificacoes($f, $anoDesempenho),
            'comparacao' => $this->indicadores->comparacaoAnos($f),
            'tabela' => $this->indicadores->tabelaEscolas($f, $anoDesempenho),
        ];
    }

    private function opcoesFiltro(Filtros $f): array
    {
        return [
            'opcoes' => [
                'municipios' => Municipio::visiveis()->activos()->orderBy('nome')->get(['id', 'nome']),
                'escolas' => Escola::visiveis()->activas()->when($f->municipioId, fn ($q) => $q->where('municipio_id', $f->municipioId))->orderBy('nome')->get(['id', 'nome']),
                'tipologias' => TipologiaEscola::ordenados()->get(['id', 'nome']),
                'niveis' => NivelEnsino::ordenados()->get(['id', 'nome']),
                'anos' => AnoLectivo::recentes()->get(['id', 'designacao']),
                'classes' => Classe::with('nivelEnsino')->when($f->nivelId, fn ($q) => $q->where('nivel_ensino_id', $f->nivelId))->ordenadas()->get(),
            ],
        ];
    }
}
