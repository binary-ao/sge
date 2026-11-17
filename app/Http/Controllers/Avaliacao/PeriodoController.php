<?php

namespace App\Http\Controllers\Avaliacao;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Avaliacao;
use App\Models\Nota;
use App\Models\PeriodoLancamento;
use App\Models\ResultadoAvaliacao;
use App\Models\Turma;
use App\Services\Avaliacao\ServicoResultados;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fecho e reabertura de períodos de lançamento (por escola) e recálculo controlado.
 * Após o fecho, as notas ficam bloqueadas; a reabertura exige permissão e motivo.
 */
class PeriodoController extends Controller
{
    public function index(Request $request, ContextoEscola $ctx): View
    {
        $anoId = $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id;
        $registos = PeriodoLancamento::with('alteradoPor')->where('ano_lectivo_id', $anoId)->get()->keyBy('periodo');
        $turmas = Turma::with('classe', 'curso')->where('ano_lectivo_id', $anoId)->get()->sortBy('designacao');

        $notasPorPeriodo = Nota::join('avaliacoes', 'avaliacoes.id', '=', 'notas.avaliacao_id')
            ->whereIn('avaliacoes.turma_id', $turmas->pluck('id'))
            ->selectRaw('avaliacoes.periodo, count(*) as total')->groupBy('avaliacoes.periodo')->pluck('total', 'periodo');

        $desactualizados = ResultadoAvaliacao::join('configuracoes_avaliacao as c', 'c.id', '=', 'resultados_avaliacao.configuracao_avaliacao_id')
            ->whereIn('resultados_avaliacao.turma_id', $turmas->pluck('id'))
            ->whereColumn('resultados_avaliacao.configuracao_versao', '<', 'c.versao')
            ->selectRaw('resultados_avaliacao.turma_id, count(*) as total')->groupBy('resultados_avaliacao.turma_id')->pluck('total', 'turma_id');

        return view('avaliacao.periodos.index', [
            'anoId' => $anoId,
            'anos' => AnoLectivo::recentes()->get(),
            'periodos' => collect(PeriodoLancamento::PERIODOS)->map(fn ($rotulo, $p) => (object) [
                'periodo' => $p, 'rotulo' => $rotulo, 'registo' => $registos[$p] ?? null,
                'fechado' => ($registos[$p]->estado ?? 'aberto') === 'fechado', 'notas' => $notasPorPeriodo[$p] ?? 0,
            ]),
            'turmas' => $turmas,
            'desactualizados' => $desactualizados,
        ]);
    }

    public function fechar(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        $dados = $request->validate([
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'periodo' => ['required', 'integer', 'between:1,4'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);
        $this->exigirEscrita($ctx);
        $registo = PeriodoLancamento::firstOrNew(['ano_lectivo_id' => $dados['ano_lectivo_id'], 'periodo' => $dados['periodo']]);
        if ($registo->estado === 'fechado') {
            return back()->with('aviso', 'O período já se encontra fechado.');
        }
        $registo->fill(['estado' => 'fechado', 'alterado_por' => $request->user()->id, 'alterado_em' => now(), 'motivo' => $dados['motivo'] ?? null])->save();
        Auditoria::registar('fechar', 'Fechou o período: '.PeriodoLancamento::PERIODOS[$dados['periodo']], $registo, ['motivo' => $dados['motivo'] ?? null]);

        return back()->with('sucesso', PeriodoLancamento::PERIODOS[$dados['periodo']].' fechado. As notas ficam bloqueadas.');
    }

    public function reabrir(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        $dados = $request->validate([
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'periodo' => ['required', 'integer', 'between:1,4'],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], ['motivo.required' => 'A reabertura exige a indicação do motivo.'], ['motivo' => 'motivo']);
        $this->exigirEscrita($ctx);
        $registo = PeriodoLancamento::where('ano_lectivo_id', $dados['ano_lectivo_id'])->where('periodo', $dados['periodo'])->firstOrFail();
        $registo->update(['estado' => 'aberto', 'alterado_por' => $request->user()->id, 'alterado_em' => now(), 'motivo' => $dados['motivo']]);
        Auditoria::registar('reabrir', 'Reabriu o período: '.PeriodoLancamento::PERIODOS[$dados['periodo']], $registo, ['motivo' => $dados['motivo']]);

        return back()->with('sucesso', PeriodoLancamento::PERIODOS[$dados['periodo']].' reaberto. A operação ficou registada no histórico.');
    }

    /**
     * Recálculo controlado de uma turma com a versão actual das configurações.
     * Não altera notas; só os resultados calculados. Fica registado com o motivo.
     */
    public function recalcular(Request $request, Turma $turma, ServicoResultados $servico, ContextoEscola $ctx): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']], [], ['motivo' => 'motivo']);
        $this->exigirEscrita($ctx);
        $total = $servico->recalcularTurma($turma, null, $request->user()->id);
        Auditoria::registar('recalcular', "Recálculo controlado dos resultados da turma {$turma->designacao}", $turma,
            ['motivo' => $dados['motivo'], 'calculos' => $total]);

        return back()->with('sucesso', "Resultados da turma {$turma->designacao} recalculados ({$total} disciplina(s)·aluno).");
    }

    private function exigirEscrita(ContextoEscola $ctx): void
    {
        abort_unless($ctx->podeEscreverDadosOperacionais(), 403);
    }
}
