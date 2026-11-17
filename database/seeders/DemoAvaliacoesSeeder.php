<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\PlanoCurricular;
use App\Models\Turma;
use App\Services\Avaliacao\DefinicaoAvaliacao;
use App\Services\Avaliacao\MotorCalculo;
use App\Services\Avaliacao\MotorRegras;
use App\Services\Avaliacao\ServicoConfiguracao;
use App\Support\Sequencia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Notas e resultados DEMONSTRATIVOS (fictícios).
 *
 *  - 2025/2026 (ano encerrado): turmas da classe anterior, notas dos 3 trimestres e exames,
 *    resultados por disciplina e situação final; períodos fechados. Alguns alunos ficam
 *    retidos (repetentes ou desistentes em 2026/2027), para dar coerência ao histórico.
 *  - 2026/2027 (ano em curso): AC1 e AC2 do I Trimestre.
 *
 * Todos os resultados são calculados pelo MotorCalculo e pelo MotorRegras, com as
 * configurações de avaliação semeadas.
 */
class DemoAvaliacoesSeeder extends Seeder
{
    private ServicoConfiguracao $configuracoes;
    private MotorCalculo $motor;
    private MotorRegras $regras;
    private string $agora;

    public function run(ServicoConfiguracao $configuracoes, MotorCalculo $motor, MotorRegras $regras): void
    {
        mt_srand(424);
        $this->configuracoes = $configuracoes;
        $this->motor = $motor;
        $this->regras = $regras;
        $this->agora = now()->toDateTimeString();

        $anterior = AnoLectivo::where('designacao', '2025/2026')->first();
        $actual = AnoLectivo::where('actual', true)->first();

        DB::transaction(function () use ($anterior, $actual) {
            if ($anterior) {
                foreach (Escola::with('niveisEnsino')->orderBy('codigo')->get() as $escola) {
                    $this->anoAnterior($escola, $anterior, $actual);
                }
            }
            foreach (Turma::where('ano_lectivo_id', $actual->id)->with('classe.nivelEnsino')->get() as $turma) {
                $this->primeiroTrimestre($turma);
            }
        });
    }

    /* ======================================================= Ano anterior */

    private function anoAnterior(Escola $escola, AnoLectivo $anterior, AnoLectivo $actual): void
    {
        $niveis = $escola->niveisEnsino->pluck('id')->all();
        $turmasActuais = Turma::with('classe.nivelEnsino', 'matriculas')->where('escola_id', $escola->id)->where('ano_lectivo_id', $actual->id)->get();

        foreach ($turmasActuais as $turmaActual) {
            $classeAnterior = $this->classeAnterior($turmaActual->classe);
            if (! $classeAnterior || ! in_array($classeAnterior->nivel_ensino_id, $niveis, true)) {
                continue;
            }
            $curso = $classeAnterior->nivelEnsino->exige_curso && $classeAnterior->nivel_ensino_id === $turmaActual->classe->nivel_ensino_id
                ? $turmaActual->curso_id : null;
            if ($classeAnterior->nivelEnsino->exige_curso && ! $curso) {
                continue;
            }

            $turma = new Turma(['ano_lectivo_id' => $anterior->id, 'classe_id' => $classeAnterior->id, 'curso_id' => $curso,
                'turno_id' => $turmaActual->turno_id, 'nome' => 'A', 'sala' => $turmaActual->sala, 'capacidade' => 40, 'activo' => true,
                'director_turma_id' => $turmaActual->director_turma_id]);
            $turma->escola_id = $escola->id;
            if (Turma::where(['escola_id' => $escola->id, 'ano_lectivo_id' => $anterior->id, 'classe_id' => $classeAnterior->id, 'nome' => 'A'])->exists()) {
                continue;
            }
            $turma->save();
            $turma->setRelation('classe', $classeAnterior);

            $config = $this->configuracoes->resolver($anterior->id, $classeAnterior->id);
            $plano = PlanoCurricular::resolver($escola->id, $anterior->id, $classeAnterior->id, $curso);
            if (! $config || ! $plano) {
                continue;
            }
            $definicao = $this->configuracoes->definicao($config);
            $retencao = $classeAnterior->regime !== 'sem_retencao';
            $anoNum = (int) $anterior->data_inicio->format('Y');

            // Alunos que estão hoje na classe seguinte: aprovaram em 2025/2026.
            $alunos = [];
            foreach ($turmaActual->matriculas as $m) {
                $alunos[] = [$m->aluno_id, 'aprovado'];
            }
            // Alunos retidos (fictícios), que hoje repetem a classe ou desistiram.
            if ($retencao) {
                for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
                    $alunos[] = [$this->novoAluno($escola, $turmaActual->matriculas->first()?->aluno_id), 'retido'];
                }
            }

            $matriculas = [];
            foreach ($alunos as [$alunoId, $perfil]) {
                $m = new Matricula(['aluno_id' => $alunoId, 'ano_lectivo_id' => $anterior->id, 'classe_id' => $classeAnterior->id,
                    'curso_id' => $curso, 'turma_id' => $turma->id, 'turno_id' => $turma->turno_id,
                    'numero' => Sequencia::formatar($anoNum, Sequencia::proximo($escola->id, 'matricula', $anoNum)),
                    'tipo' => 'confirmacao', 'data_matricula' => $anterior->data_inicio->copy()->subDays(mt_rand(5, 30))->toDateString(), 'estado' => 'concluida']);
                $m->escola_id = $escola->id;
                $m->save();
                $m->setRelation('classe', $classeAnterior);
                $matriculas[] = [$m, $perfil];
            }

            $finais = $this->gerarNotasEResultados($turma, $plano, $config, $definicao, $matriculas, [1, 2, 3, 4], true);

            // Retidos: repetem a classe em 2026/2027 se houver turma, senão desistem.
            foreach ($matriculas as [$m, $perfil]) {
                if ($perfil !== 'retido' || config("avaliacao.situacoes.{$finais[$m->id]}.positiva")) {
                    continue;
                }
                $turmaRepetir = Turma::where('escola_id', $escola->id)->where('ano_lectivo_id', $actual->id)
                    ->where('classe_id', $classeAnterior->id)->where('curso_id', $curso)->first();
                if ($turmaRepetir && $turmaRepetir->matriculasActivas()->count() < $turmaRepetir->capacidade) {
                    $anoActual = (int) $actual->data_inicio->format('Y');
                    $nova = new Matricula(['aluno_id' => $m->aluno_id, 'ano_lectivo_id' => $actual->id, 'classe_id' => $classeAnterior->id,
                        'curso_id' => $curso, 'turma_id' => $turmaRepetir->id, 'turno_id' => $turmaRepetir->turno_id,
                        'numero' => Sequencia::formatar($anoActual, Sequencia::proximo($escola->id, 'matricula', $anoActual)),
                        'tipo' => 'confirmacao', 'data_matricula' => $actual->data_inicio->copy()->subDays(mt_rand(5, 30))->toDateString(),
                        'estado' => 'activa', 'observacoes' => 'Repetente (resultado de 2025/2026).']);
                    $nova->escola_id = $escola->id;
                    $nova->save();
                } else {
                    Aluno::where('id', $m->aluno_id)->update(['estado' => 'desistente', 'data_estado' => $anterior->data_fim->toDateString()]);
                }
            }
        }

        // O ano anterior está encerrado: períodos fechados.
        foreach ([1, 2, 3, 4] as $p) {
            DB::table('periodos_lancamento')->insertOrIgnore([
                'escola_id' => $escola->id, 'ano_lectivo_id' => $anterior->id, 'periodo' => $p, 'estado' => 'fechado',
                'alterado_em' => $anterior->data_fim, 'motivo' => 'Ano lectivo encerrado (dados demonstrativos).',
                'created_at' => $this->agora, 'updated_at' => $this->agora,
            ]);
        }
    }

    private function classeAnterior(Classe $c): ?Classe
    {
        $nivel = $c->nivelEnsino->codigo;
        $porNivel = fn (string $n, int|string $k) => Classe::whereHas('nivelEnsino', fn ($q) => $q->where('codigo', $n))
            ->where(is_int($k) ? 'numero' : 'nome', $k)->with('nivelEnsino')->first();

        return match (true) {
            $c->nome === 'Pós-Alfabetização' => $porNivel('ADUL', 'Alfabetização — Módulo 2'),
            $c->numero === null => null,
            $c->numero === 1 => $porNivel('PRE', 'Iniciação'),
            $c->numero === 7 => $porNivel('PRIM', 6),
            $c->numero === 10 && $nivel === 'ESG2' => $porNivel('ESG1', 9),
            $c->numero === 10 => null, // ETP: alunos vindos de outras escolas
            default => $porNivel($nivel, $c->numero - 1),
        };
    }

    private function novoAluno(Escola $escola, ?int $modeloId): int
    {
        $nomes = ['Adelino', 'Bráulio', 'Custódio', 'Delfina', 'Eduardo', 'Filomena', 'Gervásio', 'Henriqueta', 'Inácio', 'Jacinta', 'Laurinda', 'Mariano'];
        $apelidos = ['Lutete', 'Mabiala', 'Ngunga', 'Quissama', 'Sacala', 'Tomás', 'Vemba', 'Zua', 'Kiluange', 'Malungo'];
        $modelo = $modeloId ? Aluno::find($modeloId) : null;
        $genero = mt_rand(0, 1) ? 'M' : 'F';
        $a = new Aluno([
            'nome' => $nomes[mt_rand(0, count($nomes) - 1)].' '.$apelidos[mt_rand(0, count($apelidos) - 1)].' '.$apelidos[mt_rand(0, count($apelidos) - 1)],
            'genero' => $genero,
            'data_nascimento' => $modelo?->data_nascimento?->copy()->subYear()->toDateString() ?? '2012-05-10',
            'naturalidade' => $escola->comuna, 'nacionalidade' => 'Angolana', 'endereco' => $escola->comuna,
        ]);
        $a->escola_id = $escola->id;
        $a->numero_processo = Sequencia::formatar(0, Sequencia::proximo($escola->id, 'aluno'), 6);
        $a->estado = 'activo';
        $a->save();

        return $a->id;
    }

    /* =================================================== Ano em curso (I T) */

    private function primeiroTrimestre(Turma $turma): void
    {
        $config = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id);
        $plano = $turma->planoCurricular();
        if (! $config || ! $plano) {
            return;
        }
        $matriculas = Matricula::with('classe')->where('turma_id', $turma->id)->where('estado', 'activa')->get()
            ->map(fn ($m) => [$m, 'actual'])->all();
        $this->gerarNotasEResultados($turma, $plano, $config, $this->configuracoes->definicao($config), $matriculas, [1], false, 2);
    }

    /* ============================================ Notas, cálculo e gravação */

    /**
     * Gera notas, grava-as em lote e calcula os resultados com o motor.
     * Devolve [matricula_id => situacao final].
     */
    private function gerarNotasEResultados(Turma $turma, PlanoCurricular $plano, $config, DefinicaoAvaliacao $def, array $matriculas,
        array $periodos, bool $calcularFinal, ?int $limiteItens = null): array
    {
        $disciplinas = $plano->disciplinas;
        // Configuração por disciplina (uma configuração específica da disciplina tem prioridade), como no cálculo real.
        $porDisciplina = [];
        foreach ($disciplinas as $d) {
            $cd = $this->configuracoes->resolver($turma->ano_lectivo_id, $turma->classe_id, $d->id) ?? $config;
            $porDisciplina[$d->id] = [$cd, $this->configuracoes->definicao($cd)];
        }
        $max = $config->escala->nota_maxima;
        $min = $def->notaMinimaAprovacao;
        $qualitativa = $def->tipoClassificacao === 'qualitativa';
        $intervalos = collect($def->intervalos);
        $ajustar = function (float $v) use ($max, $qualitativa, $intervalos) {
            $v = max(0, min($max, round($v)));
            if ($qualitativa) {
                $i = $intervalos->first(fn ($i) => $v >= $i['minimo'] - 1e-9);

                return (float) ($i['valor_referencia'] ?? $v);
            }

            return (float) $v;
        };

        // Colunas (avaliações) da turma
        $colunas = [];
        foreach ($disciplinas as $d) {
            foreach ($periodos as $p) {
                foreach ($porDisciplina[$d->id][1]->componentes as $c) {
                    $aplica = $c['momento'] === 'exame' ? $p === 4 : ($p <= 3 && DefinicaoAvaliacao::aplicaAoTrimestre($c['trimestres'], $p));
                    if (! $aplica) {
                        continue;
                    }
                    $itens = $c['tipo'] === 'lista' ? min($c['itens_previstos'], $limiteItens ?? 99) : 1;
                    for ($i = 1; $i <= $itens; $i++) {
                        $colunas[] = ['escola_id' => $turma->escola_id, 'turma_id' => $turma->id, 'disciplina_id' => $d->id, 'periodo' => $p,
                            'componente' => $c['codigo'], 'indice' => $i, 'created_at' => $this->agora, 'updated_at' => $this->agora];
                    }
                }
            }
        }
        if (! $colunas) {
            return [];
        }
        DB::table('avaliacoes')->insertOrIgnore($colunas);
        $ids = DB::table('avaliacoes')->where('turma_id', $turma->id)->get()
            ->mapWithKeys(fn ($a) => ["{$a->disciplina_id}:{$a->periodo}:{$a->componente}:{$a->indice}" => $a->id]);

        $notas = [];
        $resultados = [];
        $finais = [];
        $situacoes = [];

        foreach ($matriculas as [$m, $perfil]) {
            $capacidade = $max * (mt_rand(0, 100) / 100 * 0.35 + 0.55);   // desempenho base do aluno
            $lista = [];
            // Retidos: 2 a 4 disciplinas negativas
            $negativas = $perfil === 'retido' ? $disciplinas->random(min($disciplinas->count(), mt_rand(2, 4)))->pluck('id')->all() : [];
            // Alguns aprovados de classes de transição com 1 disciplina não nuclear entre 7 e 9 (transição condicional)
            $condicional = $perfil === 'aprovado' && $m->classe->regime === 'transicao' && $max >= 20 && mt_rand(1, 100) <= 8
                ? $disciplinas->where('nuclear', false)->filter(fn ($d) => ! $d->pivot->especifica)->random()?->id : null;

            foreach ($disciplinas as $d) {
                $alvo = $capacidade + (mt_rand(-10, 10) / 10) * $max * 0.08;
                if ($perfil === 'aprovado') {
                    $alvo = max($alvo, $min + $max * 0.08);         // aprovou: acima do mínimo
                }
                if (in_array($d->id, $negativas, true)) {
                    $alvo = $min - $max * (mt_rand(5, 30) / 100);
                }
                if ($d->id === $condicional) {
                    $alvo = $max * 0.4;                             // ex.: 8 valores
                }
                [$configD, $defD] = $porDisciplina[$d->id];
                $entrada = [];
                foreach ($periodos as $p) {
                    foreach ($defD->componentes as $c) {
                        $aplica = $c['momento'] === 'exame' ? $p === 4 : ($p <= 3 && DefinicaoAvaliacao::aplicaAoTrimestre($c['trimestres'], $p));
                        if (! $aplica) {
                            continue;
                        }
                        $itens = $c['tipo'] === 'lista' ? min($c['itens_previstos'], $limiteItens ?? 99) : 1;
                        for ($i = 1; $i <= $itens; $i++) {
                            $ruido = $perfil === 'actual' ? $max * 0.12 : $max * 0.05;
                            $v = $ajustar($alvo + (mt_rand(-100, 100) / 100) * $ruido);
                            $entrada[$p][$c['codigo']][] = $v;
                            $notas[] = ['escola_id' => $turma->escola_id, 'avaliacao_id' => $ids["{$d->id}:{$p}:{$c['codigo']}:{$i}"], 'matricula_id' => $m->id,
                                'valor' => $v, 'created_at' => $this->agora, 'updated_at' => $this->agora];
                        }
                    }
                }

                $r = $this->motor->calcular($defD, $entrada);
                foreach ($r['trimestres'] + [0 => $r['final']] as $periodo => $x) {
                    $resultados[] = [
                        'escola_id' => $turma->escola_id, 'matricula_id' => $m->id, 'turma_id' => $turma->id, 'disciplina_id' => $d->id,
                        'periodo' => $periodo, 'valores' => json_encode($x['valores']), 'classificacao' => $x['classificacao'],
                        'classificacao_qualitativa' => $x['qualitativa'], 'situacao' => $x['situacao'],
                        'configuracao_avaliacao_id' => $configD->id, 'configuracao_versao' => $configD->versao, 'calculado_em' => $this->agora,
                    ];
                }
                $lista[] = ['nome' => $d->nome, 'nuclear' => (bool) $d->nuclear, 'especifica' => (bool) $d->pivot->especifica, 'classificacao' => $r['final']['classificacao']];
            }

            if ($calcularFinal) {
                $s = $this->regras->avaliar($def->regras, $m->classe->regime, $lista, null, $def->notaMinimaAprovacao);
                $situacoes[$m->id] = $s['situacao'];
                $finais[] = [
                    'escola_id' => $turma->escola_id, 'matricula_id' => $m->id, 'ano_lectivo_id' => $turma->ano_lectivo_id,
                    'situacao' => $s['situacao'], 'rotulo' => $s['rotulo'], 'justificacao' => json_encode($s['justificacao'], JSON_UNESCAPED_UNICODE),
                    'media' => $s['media'], 'disciplinas_negativas' => $s['negativas'],
                    'configuracao_avaliacao_id' => $config->id, 'configuracao_versao' => $config->versao, 'calculado_em' => $this->agora,
                ];
            }
        }

        foreach (array_chunk($notas, 1000) as $lote) {
            DB::table('notas')->insert($lote);
        }
        foreach (array_chunk($resultados, 500) as $lote) {
            DB::table('resultados_avaliacao')->insert($lote);
        }
        foreach (array_chunk($finais, 500) as $lote) {
            DB::table('resultados_finais')->insert($lote);
        }

        return $situacoes;
    }
}
