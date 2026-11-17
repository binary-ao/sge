<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\Declaracao;
use App\Services\Administrativo\ServicoDeclaracoes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DeclaracaoController extends Controller
{
    public function index(Request $request): View
    {
        return view('administrativo.declaracoes.index', [
            'declaracoes' => Declaracao::with('aluno', 'emitidaPor')
                ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('numero', 'like', '%'.$request->q.'%')
                    ->orWhere('codigo_verificacao', $request->q)->orWhereHas('aluno', fn ($a) => $a->pesquisa($request->q))))
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $aluno = $request->filled('aluno') ? Aluno::findOrFail($request->integer('aluno')) : null;

        return view('administrativo.declaracoes.create', [
            'aluno' => $aluno,
            'matriculas' => $aluno?->matriculas()->with('anoLectivo', 'classe', 'turma')->where('estado', '!=', 'anulada')->orderByDesc('ano_lectivo_id')->get() ?? collect(),
            'alunos' => $aluno ? collect() : Aluno::orderBy('nome')->get(['id', 'nome', 'numero_processo']),
        ]);
    }

    public function store(Request $request, ServicoDeclaracoes $servico): RedirectResponse
    {
        $dados = $request->validate([
            'aluno_id' => ['required', 'integer'],
            'matricula_id' => ['required', 'integer'],
            'tipo' => ['required', Rule::in(array_keys(config('administrativo.declaracoes.tipos')))],
            'finalidade' => ['nullable', 'string', 'max:255'],
        ], [], ['matricula_id' => 'matrícula', 'tipo' => 'tipo de declaração']);

        $aluno = Aluno::findOrFail($dados['aluno_id']);
        $matricula = $aluno->matriculas()->findOrFail($dados['matricula_id']);
        $declaracao = $servico->emitir($matricula, $dados['tipo'], $dados['finalidade'] ?? null, $request->user()->id);

        return redirect()->route('declaracoes.index')->with('sucesso', "Declaração {$declaracao->numero} emitida.")
            ->with('descarregar', route('declaracoes.pdf', $declaracao));
    }

    public function pdf(Declaracao $declaracao): Response
    {
        $declaracao->load('emitidaPor');

        return Pdf::loadView('administrativo.declaracoes.pdf', ['d' => $declaracao, 'c' => $declaracao->conteudo])
            ->setPaper('a4', 'portrait')->download(str_replace([' ', '/'], '-', $declaracao->numero).'.pdf');
    }
}
