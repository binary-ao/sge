<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Pagamento;
use App\Models\Recibo;
use App\Services\Administrativo\ServicoPagamentos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PagamentoController extends Controller
{
    public function __construct(private ServicoPagamentos $servico) {}

    public function index(Request $request): View
    {
        $request->validate(['de' => ['nullable', 'date'], 'ate' => ['nullable', 'date']]);
        $de = $request->filled('de') ? Carbon::parse($request->de) : now()->startOfMonth();
        $ate = $request->filled('ate') ? Carbon::parse($request->ate) : now();

        $recibos = Recibo::with('aluno', 'pagamentos', 'emitidoPor')
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->when($request->filled('forma'), fn ($q) => $q->where('forma_pagamento', $request->forma))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('numero', 'like', '%'.$request->q.'%')
                ->orWhereHas('aluno', fn ($a) => $a->pesquisa($request->q))));

        $validos = (clone $recibos)->where('estado', 'emitido');
        $porForma = (clone $validos)->selectRaw('forma_pagamento, sum(total) as total')->groupBy('forma_pagamento')->pluck('total', 'forma_pagamento');

        return view('administrativo.pagamentos.index', [
            'recibos' => (clone $recibos)->orderByDesc('data')->orderByDesc('sequencial')->paginate(25)->withQueryString(),
            'de' => $de, 'ate' => $ate,
            'resumo' => [
                'total' => (float) (clone $validos)->sum('total'),
                'recibos' => (clone $validos)->count(),
                'anulados' => (clone $recibos)->where('estado', 'anulado')->count(),
                'porForma' => $porForma,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $aluno = $request->filled('aluno') ? Aluno::with('encarregado')->findOrFail($request->integer('aluno')) : null;
        $ano = AnoLectivo::actual();
        $matricula = $aluno?->matriculas()->with('classe', 'turma', 'anoLectivo')->where('ano_lectivo_id', $ano?->id)->first();

        $servicos = $aluno ? $this->servico->servicosPara($matricula?->classe_id) : collect();
        $pagos = $aluno ? Pagamento::where('aluno_id', $aluno->id)->where('ano_lectivo_id', $ano?->id)->where('estado', 'valido')->whereNotNull('mes')
            ->get()->map(fn ($p) => $p->ano_mes.'-'.$p->mes)->all() : [];

        return view('administrativo.pagamentos.create', [
            'aluno' => $aluno,
            'matricula' => $matricula,
            'ano' => $ano,
            'servicos' => $servicos,
            'meses' => $ano ? $this->servico->mesesDoAno($ano) : collect(),
            'pagos' => $pagos,
            'emDivida' => $matricula ? $this->servico->emDivida($matricula)->map(fn ($m) => $m['ano'].'-'.$m['mes'])->all() : [],
            'alunos' => $aluno ? collect() : Aluno::where('estado', 'activo')->orderBy('nome')->get(['id', 'nome', 'numero_processo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'aluno_id' => ['required', 'integer'],
            'servicos' => ['nullable', 'array'],
            'servicos.*' => ['integer'],
            'meses' => ['nullable', 'array'],
            'meses.*' => ['string', 'regex:/^\d{4}-\d{1,2}$/'],
            'mensalidade_id' => ['nullable', 'integer', 'required_with:meses'],
            'forma_pagamento' => ['required', Rule::in(array_keys(config('administrativo.pagamentos.formas')))],
            'referencia' => ['nullable', 'string', 'max:60', 'required_unless:forma_pagamento,numerario'],
            'data' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'referencia.required_unless' => 'Indique a referência da transferência ou do talão TPA.',
        ], ['forma_pagamento' => 'forma de pagamento', 'referencia' => 'referência']);

        $aluno = Aluno::findOrFail($dados['aluno_id']);   // global scope: apenas alunos da escola activa
        $matricula = $aluno->matriculas()->where('ano_lectivo_id', AnoLectivo::actual()?->id)->first();

        $itens = array_map(fn ($id) => ['servico_id' => (int) $id], $dados['servicos'] ?? []);
        foreach ($dados['meses'] ?? [] as $mm) {
            [$a, $m] = array_map('intval', explode('-', $mm));
            $itens[] = ['servico_id' => (int) $dados['mensalidade_id'], 'mes' => $m, 'ano_mes' => $a];
        }

        $recibo = $this->servico->registar($aluno, $matricula, $itens, $dados['forma_pagamento'], $dados['referencia'] ?? null,
            Carbon::parse($dados['data']), $request->user()->id);

        return redirect()->route('pagamentos.recibo', $recibo)->with('sucesso', "Pagamento registado. Recibo n.º {$recibo->numero} emitido.");
    }

    public function recibo(Recibo $recibo): View
    {
        return view('administrativo.pagamentos.recibo', ['recibo' => $recibo->load('aluno', 'pagamentos.servico', 'emitidoPor', 'anuladoPor', 'escola.municipio')]);
    }

    public function reciboPdf(Recibo $recibo): Response
    {
        $recibo->load('aluno', 'pagamentos', 'emitidoPor', 'anuladoPor', 'escola.municipio');

        return Pdf::loadView('administrativo.pagamentos.recibo-pdf', ['recibo' => $recibo])->setPaper('a5', 'portrait')
            ->download(str_replace([' ', '/'], ['-', '-'], $recibo->numero).'.pdf');
    }

    public function anular(Request $request, Recibo $recibo): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']], [], ['motivo' => 'motivo']);
        $this->servico->anular($recibo, $dados['motivo'], $request->user()->id);

        return back()->with('sucesso', "Recibo {$recibo->numero} anulado. Os pagamentos associados deixam de contar.");
    }

    public function atraso(Request $request): View
    {
        $ano = AnoLectivo::actual();
        $lista = $ano ? $this->servico->emAtraso($ano->id) : collect();

        return view('administrativo.pagamentos.atraso', [
            'lista' => $lista,
            'ano' => $ano,
            'total' => $lista->sum(fn ($l) => $l['meses']->sum('valor')),
            'meses' => $lista->sum(fn ($l) => $l['meses']->count()),
        ]);
    }
}
