<?php

namespace App\Http\Controllers\Paineis;

use App\Exports\PautaExport;
use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\TipologiaEscola;
use App\Services\Indicadores\Filtros;
use App\Services\Relatorios\ServicoRelatorios;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use App\Support\Menu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class RelatorioController extends Controller
{
    public function index(ContextoEscola $ctx): View
    {
        $user = auth()->user();
        $relatorios = collect(ServicoRelatorios::catalogo())
            ->filter(fn ($r) => Menu::temAlguma($user, $r[3]))
            ->map(fn ($r, $chave) => ['chave' => $chave, 'grupo' => $r[0], 'titulo' => $r[1], 'descricao' => $r[2], 'exigeEscola' => $r[4], 'disponivel' => $r[6]])
            ->groupBy('grupo');

        return view('relatorios.index', ['relatorios' => $relatorios, 'escolaActiva' => $ctx->escolaActiva()]);
    }

    public function show(Request $request, string $chave, ServicoRelatorios $servico, ContextoEscola $ctx): View|Response|RedirectResponse
    {
        $def = ServicoRelatorios::catalogo()[$chave] ?? abort(404);
        abort_unless(Menu::temAlguma($request->user(), $def[3]), 403);
        abort_unless($def[6], 404);
        if ($def[4] && ! $ctx->escolaActivaId()) {
            return redirect()->route('contexto.escolher')->with('aviso', 'Os relatórios escolares exigem uma escola activa.');
        }
        if ($chave === 'pautas') {
            return redirect()->route('avaliacao.pautas.index');
        }

        $filtros = Filtros::doPedido($request);
        if ($def[4]) {
            $filtros->escolaId = $ctx->escolaActivaId();
        }
        $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date', 'after_or_equal:de']]);
        $extra = [
            'turma_id' => $request->filled('turma_id') ? $request->integer('turma_id') : null,
            'agrupamento' => in_array($request->input('agrupamento'), ['aluno', 'turma', 'disciplina', 'mes'], true) ? $request->input('agrupamento') : null,
            'de' => $request->filled('de') ? \Illuminate\Support\Carbon::parse($request->input('de'))->toDateString() : null,
            'ate' => $request->filled('ate') ? \Illuminate\Support\Carbon::parse($request->input('ate'))->toDateString() : null,
        ];
        $dados = $servico->gerar($chave, $filtros, $extra) + [
            'chave' => $chave,
            'filtros' => $filtros,
            'camposFiltro' => $def[5],
            'escola' => $def[4] ? $ctx->escolaActiva() : null,
            'gerado_em' => now(),
            'utilizador' => $request->user()->nome,
        ];

        $ficheiro = Str::slug($dados['titulo'].' '.now()->format('Y-m-d'));
        if ($request->input('formato') === 'pdf') {
            Auditoria::registar('exportar', 'Exportou relatório em PDF: '.$dados['titulo']);

            return Pdf::loadView('relatorios.pdf', $dados)->setPaper('a4', count($dados['colunas']) > 5 ? 'landscape' : 'portrait')->download($ficheiro.'.pdf');
        }
        if ($request->input('formato') === 'xlsx') {
            Auditoria::registar('exportar', 'Exportou relatório em Excel: '.$dados['titulo']);

            return Excel::download(new PautaExport('relatorios.excel', $dados, $dados['titulo']), $ficheiro.'.xlsx');
        }

        return view('relatorios.show', $dados + [
            'opcoes' => [
                'municipios' => Municipio::visiveis()->orderBy('nome')->get(['id', 'nome']),
                'tipologias' => TipologiaEscola::ordenados()->get(['id', 'nome']),
                'niveis' => NivelEnsino::ordenados()->get(['id', 'nome']),
                'anos' => AnoLectivo::recentes()->get(['id', 'designacao']),
                'turmas' => in_array('turma_id', $def[5], true) ? ServicoRelatorios::turmasDaEscola($filtros->anoLectivoId) : collect(),
            ],
        ]);
    }
}
