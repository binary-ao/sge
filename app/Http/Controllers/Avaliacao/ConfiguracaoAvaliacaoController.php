<?php

namespace App\Http\Controllers\Avaliacao;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Ciclo;
use App\Models\Classe;
use App\Models\ConfiguracaoAvaliacao;
use App\Models\Disciplina;
use App\Models\EscalaClassificacao;
use App\Models\HistoricoConfiguracao;
use App\Models\ModeloFormula;
use App\Models\NivelEnsino;
use App\Models\ResultadoAvaliacao;
use App\Models\SistemaAvaliacao;
use App\Services\Avaliacao\DefinicaoAvaliacao;
use App\Services\Avaliacao\GestorConfiguracoes;
use App\Services\Avaliacao\MotorCalculo;
use App\Services\Avaliacao\MotorRegras;
use App\Services\Avaliacao\ServicoConfiguracao;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfiguracaoAvaliacaoController extends Controller
{
    public function __construct(private GestorConfiguracoes $gestor, private ServicoConfiguracao $servico) {}

    public function index(Request $request): View
    {
        $anoId = $request->integer('ano_lectivo_id') ?: AnoLectivo::actual()?->id;
        $configs = ConfiguracaoAvaliacao::with('sistema', 'nivelEnsino', 'ciclo', 'disciplina', 'classes', 'actualizadoPor', 'escala')
            ->where('ano_lectivo_id', $anoId)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('sistema_avaliacao_id')->orderBy('nivel_ensino_id')->orderBy('id')->get();

        return view('avaliacao.configuracoes.index', [
            'configs' => $configs->groupBy('sistema_avaliacao_id'),
            'sistemas' => SistemaAvaliacao::orderBy('id')->get()->keyBy('id'),
            'anos' => AnoLectivo::recentes()->get(),
            'anoId' => $anoId,
        ]);
    }

    public function show(ConfiguracaoAvaliacao $configuracao): View
    {
        $configuracao->load('sistema', 'anoLectivo', 'nivelEnsino', 'ciclo', 'disciplina', 'classes', 'componentes', 'formulas.modelo',
            'regras', 'escala.intervalos', 'criadoPor', 'actualizadoPor');

        return view('avaliacao.configuracoes.show', [
            'config' => $configuracao,
            'historico' => HistoricoConfiguracao::with('utilizador')->where('entidade', 'configuracao')
                ->where('entidade_id', $configuracao->id)->orderByDesc('versao')->get(),
            'resultados' => ResultadoAvaliacao::semFiltroEscola()->where('configuracao_avaliacao_id', $configuracao->id)->count(),
            'desactualizados' => ResultadoAvaliacao::semFiltroEscola()->where('configuracao_avaliacao_id', $configuracao->id)
                ->where('configuracao_versao', '<', $configuracao->versao)->count(),
            'anos' => AnoLectivo::recentes()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $config = new ConfiguracaoAvaliacao([
            'ano_lectivo_id' => AnoLectivo::actual()?->id, 'estado' => 'rascunho', 'arredondamento' => 'unidade',
            'tipo_classificacao' => 'quantitativa', 'variavel_trimestral' => 'MT', 'variavel_final' => 'MFD',
        ]);
        $dados = null;
        if ($request->filled('copiar')) {
            $origem = ConfiguracaoAvaliacao::findOrFail($request->integer('copiar'));
            $snap = $origem->snapshot();
            $config->fill($snap['campos']);
            $config->nome = $origem->nome.' (cópia)';
            $config->estado = 'rascunho';
            $dados = $snap;
        }

        return view('avaliacao.configuracoes.form', $this->dadosFormulario($config, $dados));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);
        $config = new ConfiguracaoAvaliacao(['ano_lectivo_id' => $dados['ano_lectivo_id'], 'disciplina_id' => $dados['disciplina_id'] ?? null]);
        $this->verificarConflitos($config, $dados);
        $config = $this->gestor->guardar($config, $dados, $dados['motivo'] ?? 'Criação da configuração.');

        return redirect()->route('avaliacao.configuracoes.show', $config)->with('sucesso', 'Configuração criada (versão 1).');
    }

    public function edit(ConfiguracaoAvaliacao $configuracao): View
    {
        return view('avaliacao.configuracoes.form', $this->dadosFormulario($configuracao, $configuracao->snapshot()));
    }

    public function update(Request $request, ConfiguracaoAvaliacao $configuracao): RedirectResponse
    {
        $dados = $this->validar($request, $configuracao);
        $this->verificarConflitos($configuracao, $dados);
        $this->gestor->guardar($configuracao, $dados, $dados['motivo']);
        Auditoria::registar('configuracao', "Alterou a configuração de avaliação \"{$configuracao->nome}\" (versão {$configuracao->versao})", $configuracao,
            ['motivo' => $dados['motivo']]);

        return redirect()->route('avaliacao.configuracoes.show', $configuracao)
            ->with('sucesso', "Configuração guardada como versão {$configuracao->versao}. Os resultados já calculados não foram alterados; utilize o recálculo controlado se necessário.");
    }

    /** Pré-visualização do cálculo com valores de teste, antes de guardar (mesmo motor PHP). */
    public function previsualizar(Request $request, MotorCalculo $motor, MotorRegras $regras): JsonResponse
    {
        $request->validate(['escala_id' => ['required', 'integer', 'exists:escalas_classificacao,id']]);
        $escala = EscalaClassificacao::with('intervalos')->findOrFail($request->integer('escala_id'));
        $definicao = DefinicaoAvaliacao::deArray($request->all(), $escala);
        if ($erros = $definicao->validar()) {
            return response()->json(['erros' => $erros], 422);
        }

        // Valores de teste: teste[1][AC] = "12; 14,5; 13" ; teste[4][EN] = "11"
        $notas = [];
        foreach ((array) $request->input('teste', []) as $periodo => $porComponente) {
            foreach ((array) $porComponente as $codigo => $texto) {
                $valores = array_values(array_filter(array_map(fn ($v) => trim(str_replace(',', '.', $v)), preg_split('/[;\s]+/', (string) $texto)), 'is_numeric'));
                if ($valores) {
                    $notas[(int) $periodo][strtoupper($codigo)] = array_map('floatval', $valores);
                }
            }
        }
        $r = $motor->calcular($definicao, $notas);

        // Simulação da regra de transição para um aluno com esta classificação em todas as disciplinas.
        $final = $r['final']['classificacao'];
        $simulacao = null;
        if ($final !== null) {
            $regime = Classe::whereIn('id', (array) $request->input('classes', []))->value('regime') ?? 'transicao';
            $simulacao = $regras->avaliar($definicao->regras, $regime, [
                ['nome' => 'Disciplina de teste', 'nuclear' => false, 'especifica' => false, 'classificacao' => $final],
            ], null, $definicao->notaMinimaAprovacao);
        }

        return response()->json(['resultado' => $r, 'simulacao' => $simulacao]);
    }

    public function duplicar(Request $request, ConfiguracaoAvaliacao $configuracao): RedirectResponse
    {
        $dados = $request->validate(['ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id']], [], ['ano_lectivo_id' => 'ano lectivo']);
        $nova = $this->gestor->duplicar($configuracao, AnoLectivo::findOrFail($dados['ano_lectivo_id']));

        return redirect()->route('avaliacao.configuracoes.show', $nova)->with('sucesso', 'Configuração duplicada em rascunho. Reveja-a e active-a quando estiver pronta.');
    }

    public function alterarEstado(Request $request, ConfiguracaoAvaliacao $configuracao): RedirectResponse
    {
        $dados = $request->validate([
            'estado' => ['required', Rule::in(['activa', 'inactiva'])],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['motivo' => 'motivo']);

        if ($dados['estado'] === 'activa') {
            $conflitos = $this->servico->conflitos($configuracao, $configuracao->classes()->pluck('classes.id')->all());
            if ($conflitos) {
                return back()->with('erro', 'Já existe uma configuração activa para as mesmas classes: '.implode(', ', $conflitos).'. Desactive-a primeiro.');
            }
            $erros = DefinicaoAvaliacao::deConfiguracao($configuracao)->validar();
            if ($erros) {
                return back()->with('erro', 'A configuração tem erros e não pode ser activada: '.implode(' ', $erros));
            }
        }
        $this->gestor->alterarEstado($configuracao, $dados['estado'], $dados['motivo']);

        return back()->with('sucesso', 'Configuração '.($dados['estado'] === 'activa' ? 'activada' : 'desactivada')." (versão {$configuracao->versao}).");
    }

    public function versao(ConfiguracaoAvaliacao $configuracao, int $versao): View
    {
        $registo = HistoricoConfiguracao::with('utilizador')->where('entidade', 'configuracao')->where('entidade_id', $configuracao->id)
            ->where('versao', $versao)->firstOrFail();

        return view('avaliacao.configuracoes.versao', ['config' => $configuracao, 'registo' => $registo]);
    }

    private function dadosFormulario(ConfiguracaoAvaliacao $config, ?array $snap): array
    {
        $config->loadMissing('classes');

        return [
            'config' => $config,
            'componentes' => old('componentes', $snap['componentes'] ?? [
                ['codigo' => 'AC', 'nome' => 'Avaliações contínuas', 'tipo' => 'lista', 'momento' => 'trimestral', 'trimestres' => null, 'itens_previstos' => 3, 'nota_minima' => 0, 'nota_maxima' => 20],
                ['codigo' => 'NPT', 'nome' => 'Prova trimestral', 'tipo' => 'valor', 'momento' => 'trimestral', 'trimestres' => null, 'itens_previstos' => 1, 'nota_minima' => 0, 'nota_maxima' => 20],
            ]),
            'formulas' => old('formulas', $snap['formulas'] ?? ModeloFormula::whereIn('referencia', ['A', 'B', 'C'])->orderBy('referencia')->get()
                ->map(fn ($m) => ['codigo' => $m->codigo, 'nome' => $m->nome, 'expressao' => $m->expressao, 'nivel' => $m->nivel, 'trimestres' => null,
                    'arredondar' => $m->referencia !== 'A', 'modelo_formula_id' => $m->id])->all()),
            'regras' => old('regras', $snap['regras'] ?? [['tipo' => 'nota_minima_disciplina', 'parametros' => ['nota' => 10], 'activo' => true]]),
            'classesSeleccionadas' => array_map('intval', old('classes', $snap['classes'] ?? [])),
            'sistemas' => SistemaAvaliacao::where('activo', true)->orderBy('id')->get(),
            'anos' => AnoLectivo::recentes()->get(),
            'niveis' => NivelEnsino::ordenados()->get(),
            'ciclos' => Ciclo::orderBy('ordem')->get(),
            'classes' => Classe::with('nivelEnsino')->ordenadas()->get(),
            'disciplinas' => Disciplina::withoutGlobalScope('escola')->whereNull('escola_id')->where('activo', true)->orderBy('nome')->get(),
            'escalas' => EscalaClassificacao::with('intervalos')->where('activo', true)->get(),
            'modelos' => ModeloFormula::where('activo', true)->orderBy('referencia')->get(),
            'catalogoRegras' => config('avaliacao.regras'),
        ];
    }

    private function validar(Request $request, ?ConfiguracaoAvaliacao $config = null): array
    {
        $escala = EscalaClassificacao::find($request->integer('escala_id'));
        $tiposRegra = array_keys(config('avaliacao.regras'));

        $dados = $request->validate([
            'sistema_avaliacao_id' => ['required', 'integer', 'exists:sistemas_avaliacao,id'],
            'ano_lectivo_id' => ['required', 'integer', 'exists:anos_lectivos,id'],
            'nivel_ensino_id' => ['required', 'integer', 'exists:niveis_ensino,id'],
            'ciclo_id' => ['nullable', 'integer', Rule::exists('ciclos', 'id')->where('nivel_ensino_id', $request->integer('nivel_ensino_id'))],
            'disciplina_id' => ['nullable', 'integer', Rule::exists('disciplinas', 'id')->whereNull('escola_id')],
            'escala_id' => ['required', 'integer', 'exists:escalas_classificacao,id'],
            'nome' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'tipo_classificacao' => ['required', Rule::in(array_keys(ConfiguracaoAvaliacao::TIPOS))],
            'nota_minima_aprovacao' => ['required', 'numeric', 'min:'.($escala->nota_minima ?? 0), 'max:'.($escala->nota_maxima ?? 20)],
            'arredondamento' => ['required', Rule::in(array_keys(ConfiguracaoAvaliacao::ARREDONDAMENTOS))],
            'variavel_trimestral' => ['required', 'string', 'max:20'],
            'variavel_final' => ['required', 'string', 'max:20'],
            'estado' => [$config ? 'nullable' : 'required', Rule::in(['rascunho', 'activa'])],
            'classes' => ['required', 'array', 'min:1'],
            'classes.*' => ['integer', Rule::exists('classes', 'id')->where('nivel_ensino_id', $request->integer('nivel_ensino_id'))],
            'componentes' => ['required', 'array', 'min:1', 'max:15'],
            'componentes.*.codigo' => ['required', 'string', 'max:20'],
            'componentes.*.nome' => ['required', 'string', 'max:150'],
            'componentes.*.tipo' => ['required', Rule::in(['lista', 'valor'])],
            'componentes.*.momento' => ['required', Rule::in(['trimestral', 'exame'])],
            'componentes.*.trimestres' => ['nullable', 'string', 'max:10'],
            'componentes.*.itens_previstos' => ['nullable', 'integer', 'min:1', 'max:10'],
            'componentes.*.nota_minima' => ['required', 'numeric'],
            'componentes.*.nota_maxima' => ['required', 'numeric'],
            'formulas' => ['required', 'array', 'min:1', 'max:20'],
            'formulas.*.codigo' => ['required', 'string', 'max:20'],
            'formulas.*.nome' => ['required', 'string', 'max:150'],
            'formulas.*.expressao' => ['required', 'string', 'max:500'],
            'formulas.*.nivel' => ['required', Rule::in(['trimestral', 'anual'])],
            'formulas.*.trimestres' => ['nullable', 'string', 'max:10'],
            'formulas.*.arredondar' => ['nullable', 'boolean'],
            'formulas.*.modelo_formula_id' => ['nullable', 'integer', 'exists:modelos_formula,id'],
            'regras' => ['nullable', 'array', 'max:15'],
            'regras.*.tipo' => ['required', Rule::in($tiposRegra)],
            'regras.*.activo' => ['nullable', 'boolean'],
            'regras.*.parametros' => ['nullable', 'array'],
            'motivo' => [$config ? 'required' : 'nullable', 'string', 'min:5', 'max:1000'],
        ], [
            'motivo.required' => 'Indique o motivo da alteração (fica registado no histórico de versões).',
            'classes.*.exists' => 'As classes seleccionadas têm de pertencer ao nível de ensino indicado.',
        ], [
            'sistema_avaliacao_id' => 'sistema de avaliação', 'ano_lectivo_id' => 'ano lectivo', 'nivel_ensino_id' => 'nível de ensino',
            'escala_id' => 'escala', 'tipo_classificacao' => 'tipo de classificação', 'nota_minima_aprovacao' => 'nota mínima de aprovação',
            'variavel_trimestral' => 'variável trimestral', 'variavel_final' => 'variável final',
        ]);

        $dados['estado'] = $config ? $config->estado : $dados['estado'];
        $dados['regras'] = array_values(array_map(fn ($r) => [
            'tipo' => $r['tipo'], 'activo' => ! empty($r['activo']), 'parametros' => $this->normalizarParametros($r['tipo'], $r['parametros'] ?? []),
        ], $dados['regras'] ?? []));
        $dados['formulas'] = array_values($dados['formulas']);
        $dados['componentes'] = array_values($dados['componentes']);

        // Coerência das fórmulas (variáveis, sintaxe, ordem) — mesmo motor usado no cálculo.
        if ($erros = DefinicaoAvaliacao::deArray($dados, $escala)->validar()) {
            throw ValidationException::withMessages($erros);
        }

        return $dados;
    }

    private function verificarConflitos(ConfiguracaoAvaliacao $config, array $dados): void
    {
        if (($dados['estado'] ?? null) !== 'activa') {
            return;
        }
        $copia = clone $config;
        $copia->ano_lectivo_id = $dados['ano_lectivo_id'];
        $copia->disciplina_id = $dados['disciplina_id'] ?? null;
        if ($conflitos = $this->servico->conflitos($copia, $dados['classes'])) {
            throw ValidationException::withMessages(['classes' => 'Já existe uma configuração activa para estas classes: '.implode(', ', $conflitos).'.']);
        }
    }

    private function normalizarParametros(string $tipo, array $parametros): array
    {
        $final = [];
        foreach (config("avaliacao.regras.$tipo.parametros", []) as $chave => $def) {
            $valor = $parametros[$chave] ?? ($def['tipo'] === 'booleano' ? false : $def['padrao']);
            $final[$chave] = match ($def['tipo']) {
                'numero' => (float) str_replace(',', '.', (string) $valor),
                'inteiro' => (int) $valor,
                'booleano' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
                'regimes' => array_values(array_intersect((array) $valor, array_keys(Classe::REGIMES))),
                default => mb_substr((string) $valor, 0, 500),
            };
        }

        return $final;
    }
}
