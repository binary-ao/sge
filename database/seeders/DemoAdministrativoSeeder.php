<?php

namespace Database\Seeders;

use App\Models\AnoLectivo;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\Perfil;
use App\Models\Recibo;
use App\Models\ServicoPagavel;
use App\Models\User;
use App\Services\Administrativo\ServicoDeclaracoes;
use App\Services\Administrativo\ServicoPagamentos;
use App\Support\ContextoEscola;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Dados administrativos DEMONSTRATIVOS (fictícios) do ano lectivo em curso:
 * serviços e preços, pagamentos e recibos (alguns em atraso e um anulado),
 * faltas com justificativos e declarações emitidas.
 *
 * Os pagamentos e as declarações são criados pelos mesmos serviços usados na
 * aplicação, dentro do contexto de cada escola (isolamento incluído).
 */
class DemoAdministrativoSeeder extends Seeder
{
    public function run(ServicoPagamentos $pagamentos, ServicoDeclaracoes $declaracoes): void
    {
        mt_srand(606);
        config(['sige.auditoria_suspensa' => true]);
        $admin = User::whereHas('perfil', fn ($q) => $q->where('codigo', Perfil::SUPERUTILIZADOR))->firstOrFail();
        Auth::setUser($admin);
        $ctx = app(ContextoEscola::class);
        $ano = AnoLectivo::where('actual', true)->firstOrFail();
        $hoje = now();

        foreach (Escola::orderBy('codigo')->get() as $escola) {
            $ctx->definirEscolaActiva($escola->id);
            $this->servicos($escola);
            $this->pagamentos($pagamentos, $ano, $hoje, $admin);
            $this->faltas($escola, $ano, $hoje, $admin);
            $this->declaracoes($declaracoes, $admin);
        }

        $ctx->definirEscolaActiva(null);
        Auth::forgetUser();
        config(['sige.auditoria_suspensa' => false]);
    }

    /** Preços variam por escola (natureza) e, nas privadas, por classe. */
    private function servicos(Escola $escola): void
    {
        $lista = match ($escola->natureza) {
            'privada' => [['matricula', 'Matrícula / confirmação', 15000], ['mensalidade', 'Propina mensal', 18000], ['declaracao', 'Declaração', 2000], ['outro', 'Cartão de estudante', 1500]],
            'publico_privada' => [['matricula', 'Matrícula / confirmação', 5000], ['mensalidade', 'Comparticipação mensal', 8000], ['declaracao', 'Declaração', 1000]],
            default => [['matricula', 'Inscrição / confirmação de matrícula', 1500], ['declaracao', 'Declaração', 500], ['outro', 'Cartão de estudante', 1000]],
        };
        foreach ($lista as [$tipo, $nome, $valor]) {
            ServicoPagavel::create(['tipo' => $tipo, 'nome' => $nome, 'valor' => $valor, 'activo' => true]);
        }
        // Escola privada: propina diferente no I Ciclo
        if ($escola->natureza === 'privada') {
            foreach (\App\Models\Classe::daEscola($escola)->whereHas('nivelEnsino', fn ($q) => $q->where('codigo', 'ESG1'))->get() as $c) {
                ServicoPagavel::create(['tipo' => 'mensalidade', 'nome' => 'Propina mensal — '.$c->nome, 'classe_id' => $c->id, 'valor' => 22000, 'activo' => true]);
            }
        }
    }

    private function pagamentos(ServicoPagamentos $servico, AnoLectivo $ano, Carbon $hoje, User $admin): void
    {
        $formas = ['numerario', 'numerario', 'transferencia', 'tpa'];
        $matriculas = Matricula::with('aluno', 'anoLectivo')->where('ano_lectivo_id', $ano->id)->where('estado', 'activa')->get();
        $primeiroRecibo = null;

        foreach ($matriculas as $m) {
            $servicos = $servico->servicosPara($m->classe_id);
            $matricula = $servicos->firstWhere('tipo', 'matricula');
            $mensalidade = $servicos->firstWhere('tipo', 'mensalidade');
            $forma = $formas[mt_rand(0, 3)];
            $ref = fn ($f) => $f === 'numerario' ? null : strtoupper(substr(md5((string) mt_rand()), 0, 10));

            // Matrícula paga na data da matrícula (quase todos)
            if ($matricula && mt_rand(1, 100) <= 95) {
                $itens = [['servico_id' => $matricula->id]];
                if ($mensalidade) {
                    $itens[] = ['servico_id' => $mensalidade->id, 'mes' => 9, 'ano_mes' => (int) $ano->data_inicio->year];
                }
                $r = $servico->registar($m->aluno, $m, $itens, $forma, $ref($forma), $m->data_matricula, $admin->id);
                $primeiroRecibo ??= $r;
            }
            if (! $mensalidade) {
                continue;
            }
            // Mensalidades seguintes, pagas até ao dia de vencimento (algumas ficam em atraso)
            foreach ($servico->mesesDoAno($ano) as $mm) {
                $data = Carbon::create($mm['ano'], $mm['mes'], mt_rand(1, 9));
                if ($mm['mes'] === 9 || $data->gt($hoje)) {
                    continue;
                }
                $probabilidade = $mm['mes'] === 11 ? 70 : 88;
                if (mt_rand(1, 100) <= $probabilidade) {
                    $f = $formas[mt_rand(0, 3)];
                    $servico->registar($m->aluno, $m, [['servico_id' => $mensalidade->id, 'mes' => $mm['mes'], 'ano_mes' => $mm['ano']]], $f, $ref($f), $data, $admin->id);
                }
            }
        }

        // Um recibo anulado (erro de registo), para demonstrar a anulação com histórico.
        if ($primeiroRecibo && mt_rand(1, 100) <= 60) {
            $servico->anular($primeiroRecibo, 'Pagamento registado em duplicado (dados demonstrativos).', $admin->id);
        }
    }

    private function faltas(Escola $escola, AnoLectivo $ano, Carbon $hoje, User $admin): void
    {
        $dias = [];
        for ($d = $ano->data_inicio->copy(); $d->lte($hoje->copy()->subDay()); $d->addDay()) {
            if ($d->isWeekday()) {
                $dias[] = $d->toDateString();
            }
        }
        if (! $dias) {
            return;
        }
        $agora = now()->toDateTimeString();
        $turmas = \App\Models\Turma::where('ano_lectivo_id', $ano->id)->get();
        $linhas = [];
        $motivos = ['Doença', 'Assunto familiar', 'Consulta médica', 'Falta de transporte', null, null, null];

        foreach ($turmas as $turma) {
            $disciplinas = $turma->planoCurricular()?->disciplinas->pluck('id')->all() ?? [];
            $professores = \App\Models\AtribuicaoProfessor::where('turma_id', $turma->id)->pluck('professor_id', 'disciplina_id');
            if (! $disciplinas) {
                continue;
            }
            $matriculas = $turma->matriculasActivas()->pluck('id')->all();
            $assiduos = array_flip(array_slice($matriculas, 0, 1));          // pelo menos um aluno sem faltas
            $faltosos = array_flip(count($matriculas) > 6 ? [$matriculas[array_rand($matriculas)]] : []);
            foreach ($matriculas as $mid) {
                if (isset($assiduos[$mid])) {
                    continue;
                }
                $n = isset($faltosos[$mid]) ? mt_rand(45, 70) : (mt_rand(1, 100) <= 55 ? mt_rand(1, 5) : 0);
                $usados = [];
                for ($i = 0; $i < $n; $i++) {
                    $data = $dias[mt_rand(0, count($dias) - 1)];
                    $disc = $disciplinas[mt_rand(0, count($disciplinas) - 1)];
                    if (isset($usados["$data-$disc"])) {
                        continue;
                    }
                    $usados["$data-$disc"] = true;
                    $linhas[] = ['escola_id' => $escola->id, 'matricula_id' => $mid, 'turma_id' => $turma->id, 'disciplina_id' => $disc,
                        'professor_id' => $professores[$disc] ?? null, 'data' => $data, 'tempos' => isset($faltosos[$mid]) ? 2 : mt_rand(1, 2),
                        'motivo' => $motivos[mt_rand(0, count($motivos) - 1)], 'justificacao' => 'nao_justificada',
                        'registado_por' => $admin->id, 'created_at' => $agora, 'updated_at' => $agora];
                }
            }
        }
        foreach (array_chunk($linhas, 1000) as $lote) {
            DB::table('faltas')->insert($lote);
        }

        // Justificativos: cerca de 30% das faltas com motivo
        $faltas = DB::table('faltas')->where('escola_id', $escola->id)->whereNotNull('motivo')->get();
        $justificativos = [];
        foreach ($faltas as $f) {
            if (mt_rand(1, 100) > 55) {
                continue;
            }
            $sorte = mt_rand(1, 100);
            $estado = $sorte <= 60 ? 'aprovado' : ($sorte <= 85 ? 'pendente' : 'rejeitado');
            $justificativos[] = ['escola_id' => $escola->id, 'falta_id' => $f->id, 'motivo' => $f->motivo.($f->motivo === 'Doença' ? ' — atestado médico apresentado.' : '.'),
                'estado' => $estado, 'submetido_por' => $admin->id, 'analisado_por' => $estado === 'pendente' ? null : $admin->id,
                'analisado_em' => $estado === 'pendente' ? null : $agora, 'observacao' => $estado === 'rejeitado' ? 'Sem comprovativo.' : null,
                'created_at' => $agora, 'updated_at' => $agora];
            DB::table('faltas')->where('id', $f->id)->update(['justificacao' => $estado]);
        }
        foreach (array_chunk($justificativos, 1000) as $lote) {
            DB::table('justificativos_falta')->insert($lote);
        }
    }

    private function declaracoes(ServicoDeclaracoes $servico, User $admin): void
    {
        $matriculas = Matricula::where('estado', 'activa')->inRandomOrder(mt_rand())->limit(3)->get();
        $tipos = ['matricula', 'frequencia', 'notas'];
        $finalidades = ['para efeitos de abono de família', 'para apresentar na entidade empregadora do encarregado', null];
        foreach ($matriculas as $i => $m) {
            try {
                $servico->emitir($m, $tipos[$i % 3], $finalidades[$i % 3], $admin->id);
            } catch (\Illuminate\Validation\ValidationException) {
                $servico->emitir($m, 'matricula', null, $admin->id);
            }
        }
    }
}
