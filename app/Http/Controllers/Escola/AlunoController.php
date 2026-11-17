<?php

namespace App\Http\Controllers\Escola;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AlunoDocumento;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Encarregado;
use App\Models\Matricula;
use App\Models\Turma;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use App\Support\Sequencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlunoController extends Controller
{
    use RestringeProfessor;

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $ano = AnoLectivo::actual();
        $turmasPermitidas = $this->turmasPermitidas();
        $estado = $request->input('estado', 'activo');

        $alunos = Aluno::query()
            ->with(['matriculas' => fn ($q) => $q->where('ano_lectivo_id', $ano?->id)->with('classe', 'turma', 'curso')])
            ->pesquisa($request->q)
            ->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->when($request->filled('genero'), fn ($q) => $q->where('genero', $request->genero))
            ->when($request->filled('classe_id'), fn ($q) => $q->whereHas('matriculas', fn ($m) => $m->where('ano_lectivo_id', $ano?->id)->where('classe_id', $request->integer('classe_id'))))
            ->when($request->filled('turma_id'), fn ($q) => $q->whereHas('matriculas', fn ($m) => $m->where('turma_id', $request->integer('turma_id'))))
            ->when($request->input('matricula') === 'sem', fn ($q) => $q->whereDoesntHave('matriculas', fn ($m) => $m->where('ano_lectivo_id', $ano?->id)))
            ->when($turmasPermitidas !== null, fn ($q) => $q->whereHas('matriculas', fn ($m) => $m->whereIn('turma_id', $turmasPermitidas)))
            ->orderBy('nome')->paginate(25)->withQueryString();

        $escola = $ctx->escolaActiva();

        return view('escola.alunos.index', [
            'alunos' => $alunos,
            'ano' => $ano,
            'classes' => Classe::with('nivelEnsino')->daEscola($escola)->where('activo', true)->ordenadas()->get(),
            'turmas' => Turma::with('classe', 'curso')->where('ano_lectivo_id', $ano?->id)
                ->when($turmasPermitidas !== null, fn ($q) => $q->whereIn('id', $turmasPermitidas))->get()->sortBy('designacao'),
            'totais' => [
                'activos' => Aluno::where('estado', 'activo')->count(),
                'semMatricula' => Aluno::where('estado', 'activo')->whereDoesntHave('matriculas', fn ($m) => $m->where('ano_lectivo_id', $ano?->id))->count(),
            ],
        ]);
    }

    public function show(Aluno $aluno): View
    {
        $this->exigirAlunoPermitido($aluno);
        $aluno->load(['encarregado', 'documentos', 'matriculas' => fn ($q) => $q->with('anoLectivo', 'classe', 'curso', 'turma', 'turno')->orderByDesc('ano_lectivo_id')]);

        // Boletins (classificações por disciplina e período) das matrículas com turma.
        $construtor = app(\App\Services\Avaliacao\ConstrutorPautas::class);
        $boletins = $aluno->matriculas->filter(fn ($m) => $m->turma_id && $m->estado !== 'anulada')->take(3)
            ->mapWithKeys(fn ($m) => [$m->id => $construtor->boletim($m)]);

        $actual = $aluno->matriculas->firstWhere('ano_lectivo_id', AnoLectivo::actual()?->id);
        $user = auth()->user();

        return view('escola.alunos.show', [
            'assiduidade' => $actual && ($user->can('faltas.ver') || $user->can('faltas.registar')) ? app(\App\Services\Administrativo\ServicoFrequencia::class)->resumo($actual) : null,
            'faltasRecentes' => $actual ? \App\Models\Falta::with('disciplina')->where('matricula_id', $actual->id)->latest('data')->limit(8)->get() : collect(),
            'recibos' => $user->can('pagamentos.ver') ? \App\Models\Recibo::with('pagamentos')->where('aluno_id', $aluno->id)->latest('data')->limit(10)->get() : null,
            'emDivida' => $actual && $user->can('pagamentos.ver') ? app(\App\Services\Administrativo\ServicoPagamentos::class)->emDivida($actual) : collect(),
            'declaracoes' => $user->can('declaracoes.emitir') ? \App\Models\Declaracao::where('aluno_id', $aluno->id)->latest('id')->get() : null,
            'boletins' => $boletins,
            'aluno' => $aluno,
            'ano' => AnoLectivo::actual(),
            'tiposDocumento' => AlunoDocumento::TIPOS,
        ]);
    }

    public function create(): View
    {
        return view('escola.alunos.form', ['aluno' => new Aluno(['nacionalidade' => 'Angolana', 'tipo_documento' => 'bi']), 'encarregado' => new Encarregado]);
    }

    public function store(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        [$dadosAluno, $dadosEnc] = $this->validar($request);
        $escolaId = $ctx->escolaActivaId();

        $aluno = DB::transaction(function () use ($dadosAluno, $dadosEnc, $escolaId) {
            $encarregado = $dadosEnc ? Encarregado::create($dadosEnc) : null;
            $aluno = new Aluno($dadosAluno + ['encarregado_id' => $encarregado?->id]);
            $aluno->numero_processo = Sequencia::formatar(0, Sequencia::proximo($escolaId, 'aluno'), 6);
            $aluno->estado = 'activo';
            $aluno->data_estado = now()->toDateString();
            $aluno->save();

            return $aluno;
        });

        if ($request->user()->can('matriculas.gerir') && $request->input('accao') === 'matricular') {
            return redirect()->route('matriculas.create', ['aluno' => $aluno->id])
                ->with('sucesso', "Aluno registado com o processo n.º {$aluno->numero_processo}. Efectue agora a matrícula.");
        }

        return redirect()->route('alunos.show', $aluno)->with('sucesso', "Aluno registado com o processo n.º {$aluno->numero_processo}.");
    }

    public function edit(Aluno $aluno): View
    {
        return view('escola.alunos.form', ['aluno' => $aluno, 'encarregado' => $aluno->encarregado ?? new Encarregado]);
    }

    public function update(Request $request, Aluno $aluno): RedirectResponse
    {
        [$dadosAluno, $dadosEnc] = $this->validar($request, $aluno);

        DB::transaction(function () use ($aluno, $dadosAluno, $dadosEnc) {
            if ($dadosEnc) {
                if ($aluno->encarregado) {
                    $aluno->encarregado->update($dadosEnc);
                } else {
                    $dadosAluno['encarregado_id'] = Encarregado::create($dadosEnc)->id;
                }
            }
            $aluno->update($dadosAluno);
        });

        return redirect()->route('alunos.show', $aluno)->with('sucesso', 'Ficha do aluno actualizada.');
    }

    /**
     * Altera a situação do aluno (Activo, Transferido, Desistente, Concluído) e
     * reflecte-a na matrícula do ano lectivo actual.
     */
    public function alterarEstado(Request $request, Aluno $aluno): RedirectResponse
    {
        $dados = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Aluno::ESTADOS)), Rule::notIn([$aluno->estado])],
            'data_estado' => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'max:500'],
        ], ['estado.not_in' => 'O aluno já se encontra nesta situação.'], ['data_estado' => 'data', 'motivo' => 'motivo']);

        $mapaMatricula = ['activo' => 'activa', 'transferido' => 'transferida', 'desistente' => 'desistente', 'concluido' => 'concluida'];

        DB::transaction(function () use ($aluno, $dados, $mapaMatricula) {
            $anterior = $aluno->estado;
            $aluno->forceFill(['estado' => $dados['estado'], 'data_estado' => $dados['data_estado']])->save();

            $matricula = $aluno->matriculas()->where('ano_lectivo_id', AnoLectivo::actual()?->id)->where('estado', '!=', 'anulada')->first();
            $matricula?->update(['estado' => $mapaMatricula[$dados['estado']]]);

            Auditoria::registar('actualizar', "Alterou a situação do aluno {$aluno->nome}: {$anterior} → {$dados['estado']}", $aluno,
                ['motivo' => $dados['motivo'], 'data' => $dados['data_estado'], 'matricula' => $matricula?->numero]);
        });

        return back()->with('sucesso', 'Situação do aluno alterada para "'.Aluno::ESTADOS[$dados['estado']].'".');
    }

    /** @return array{0: array, 1: ?array} */
    private function validar(Request $request, ?Aluno $aluno = null): array
    {
        $escolaId = app(ContextoEscola::class)->escolaActivaId();
        try {
            $menor = $request->filled('data_nascimento') && $request->date('data_nascimento')->age < 18;
        } catch (\Throwable) {
            $menor = false; // data inválida: a regra "date" devolve o erro adequado
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'genero' => ['required', Rule::in(['M', 'F'])],
            'data_nascimento' => ['required', 'date', 'before:-3 years', 'after:-100 years'],
            'naturalidade' => ['nullable', 'string', 'max:120'],
            'nacionalidade' => ['required', 'string', 'max:60'],
            'tipo_documento' => ['nullable', Rule::in(array_keys(Aluno::TIPOS_DOCUMENTO)), 'required_with:numero_identificacao'],
            'numero_identificacao' => ['nullable', 'string', 'max:30',
                Rule::unique('alunos', 'numero_identificacao')->where('escola_id', $escolaId)->ignore($aluno)],
            'endereco' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'necessidades_especiais' => ['nullable', 'string', 'max:1000'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            // Encarregado de educação: obrigatório para menores de idade.
            'encarregado.nome' => [$menor ? 'required' : 'nullable', 'string', 'max:150'],
            'encarregado.parentesco' => ['nullable', 'string', 'max:40'],
            'encarregado.telefone' => [$menor ? 'required' : 'nullable', 'string', 'max:30'],
            'encarregado.telefone_alternativo' => ['nullable', 'string', 'max:30'],
            'encarregado.email' => ['nullable', 'email', 'max:150'],
            'encarregado.profissao' => ['nullable', 'string', 'max:100'],
            'encarregado.numero_identificacao' => ['nullable', 'string', 'max:30'],
            'encarregado.endereco' => ['nullable', 'string', 'max:255'],
        ], [
            'data_nascimento.before' => 'A data de nascimento não é válida para um aluno.',
            'encarregado.nome.required' => 'O nome do encarregado de educação é obrigatório para alunos menores de idade.',
            'encarregado.telefone.required' => 'O telefone do encarregado de educação é obrigatório para alunos menores de idade.',
        ], [
            'data_nascimento' => 'data de nascimento', 'tipo_documento' => 'tipo de documento',
            'numero_identificacao' => 'número de identificação', 'necessidades_especiais' => 'necessidades educativas especiais',
            'encarregado.email' => 'e-mail do encarregado',
        ]);

        $encarregado = array_filter($dados['encarregado'] ?? [], fn ($v) => $v !== null && $v !== '');
        unset($dados['encarregado']);
        if (! empty($dados['numero_identificacao'])) {
            $dados['numero_identificacao'] = strtoupper($dados['numero_identificacao']);
        }

        return [$dados, ! empty($encarregado['nome']) ? $encarregado : null];
    }
}
