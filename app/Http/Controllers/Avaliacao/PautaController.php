<?php

namespace App\Http\Controllers\Avaliacao;

use App\Exports\PautaExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Escola\RestringeProfessor;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\Avaliacao\ConstrutorPautas;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class PautaController extends Controller
{
    use RestringeProfessor;

    public function __construct(private ConstrutorPautas $pautas) {}

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $anoId = $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id;
        $permitidas = $this->turmasPermitidas();

        return view('avaliacao.pautas.index', [
            'anoId' => $anoId,
            'anos' => AnoLectivo::recentes()->get(),
            'turmas' => Turma::with('classe.nivelEnsino', 'curso', 'turno')->withCount('matriculasActivas')
                ->where('ano_lectivo_id', $anoId)
                ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))->get()
                ->sortBy(fn ($t) => sprintf('%03d-%03d-%s', $t->classe->nivelEnsino->ordem, $t->classe->ordem, $t->nome)),
            'classes' => $permitidas === null
                ? Classe::with('nivelEnsino')->daEscola($ctx->escolaActiva())->ordenadas()->get()
                : collect(),
        ]);
    }

    /** Pauta da turma (trimestral 1–3 ou final 0), em HTML, PDF ou Excel. */
    public function turma(Request $request, Turma $turma): View|Response
    {
        $this->exigirTurmaPermitida($turma->id);
        $periodo = (int) $request->input('periodo', 0);
        abort_unless(in_array($periodo, [0, 1, 2, 3], true), 404);
        $dados = $this->pautas->turma($turma, $periodo);

        return $this->responder($request, 'avaliacao.pautas.turma', $dados,
            Str::slug($dados['titulo'].' '.$turma->designacao.' '.$turma->anoLectivo->designacao));
    }

    public function disciplina(Request $request, Turma $turma, int $disciplina): View|Response
    {
        $this->exigirTurmaPermitida($turma->id);
        $disciplina = Disciplina::withoutGlobalScope('escola')->findOrFail($disciplina);
        abort_unless($turma->planoCurricular()?->disciplinas->contains('id', $disciplina->id), 404);
        $dados = $this->pautas->disciplina($turma, $disciplina);

        return $this->responder($request, 'avaliacao.pautas.disciplina', $dados,
            Str::slug('pauta '.$disciplina->sigla.' '.$turma->designacao.' '.$turma->anoLectivo->designacao));
    }

    public function classe(Request $request, Classe $classe, ContextoEscola $ctx): View
    {
        abort_if($this->turmasPermitidas() !== null, 403);
        $anoId = $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id;

        return view('avaliacao.pautas.classe', $this->pautas->classe($classe, $anoId) + [
            'ano' => AnoLectivo::find($anoId), 'escola' => $ctx->escolaActiva(),
        ]);
    }

    private function responder(Request $request, string $vista, array $dados, string $ficheiro): View|Response
    {
        $formato = $request->input('formato');

        if ($formato === 'pdf') {
            Auditoria::registar('exportar', 'Exportou em PDF: '.$dados['titulo'].' — '.$dados['turma']->designacao, $dados['turma']);

            return Pdf::loadView($vista.'-pdf', $dados)
                ->setPaper('a4', config('avaliacao.pautas.orientacao_pdf', 'landscape'))
                ->download($ficheiro.'.pdf');
        }
        if ($formato === 'xlsx') {
            Auditoria::registar('exportar', 'Exportou em Excel: '.$dados['titulo'].' — '.$dados['turma']->designacao, $dados['turma']);

            return Excel::download(new PautaExport($vista.'-excel', $dados, $dados['turma']->designacao), $ficheiro.'.xlsx');
        }

        return view($vista, $dados);
    }
}
