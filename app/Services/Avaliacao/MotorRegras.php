<?php

namespace App\Services\Avaliacao;

/**
 * Determina a situação final do aluno a partir das classificações finais por disciplina,
 * da frequência e das regras parametrizadas da configuração.
 *
 * Não se limita a comparar a média geral: avalia cada disciplina, as disciplinas nucleares
 * e específicas, o regime da classe (transição, Exame Nacional, terminal) e a frequência,
 * e devolve sempre a justificação do resultado.
 *
 * $disciplinas: lista de ['nome', 'nuclear' (bool), 'especifica' (bool), 'classificacao' (float|null)]
 */
class MotorRegras
{
    public function avaliar(array $regras, string $regime, array $disciplinas, ?float $frequencia, float $notaMinimaPadrao): array
    {
        $r = [];
        foreach ($regras as $regra) {
            if (! empty($regra['activo']) || ! array_key_exists('activo', $regra)) {
                $r[$regra['tipo']] = $this->parametros($regra['tipo'], $regra['parametros'] ?? []);
            }
        }
        $aplica = fn (string $tipo) => isset($r[$tipo]) && (! isset($r[$tipo]['regimes']) || in_array($regime, (array) $r[$tipo]['regimes'], true));
        $fmt = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
        $lista = fn (array $ds) => implode(', ', array_map(fn ($d) => $d['nome'].' ('.$fmt($d['classificacao']).')', $ds));
        $classificadas = array_filter($disciplinas, fn ($d) => $d['classificacao'] !== null);
        $media = $classificadas ? round(array_sum(array_column($classificadas, 'classificacao')) / count($classificadas), 2) : null;
        $positivo = $regime === 'transicao' ? 'transita' : 'aprovado';
        $negativo = $regime === 'transicao' ? 'retido' : 'reprovado';
        $j = [];

        $resultado = function (string $situacao, array $just, int $negativas = 0) use ($media) {
            return [
                'situacao' => $situacao,
                'rotulo' => config("avaliacao.situacoes.$situacao.rotulo", $situacao),
                'justificacao' => array_values($just),
                'media' => $media,
                'negativas' => $negativas,
            ];
        };

        if (isset($r['progressao_automatica'])) {
            return $resultado('progressao_automatica', ['Nível de ensino sem condições de não progressão: o aluno progride automaticamente.']);
        }

        if (! $disciplinas) {
            return $resultado('incompleto', ['Não existem disciplinas no plano curricular da turma.']);
        }

        $semNota = array_filter($disciplinas, fn ($d) => $d['classificacao'] === null);
        if ($semNota) {
            return $resultado('incompleto', ['Sem classificação final em: '.implode(', ', array_column($semNota, 'nome')).'.']);
        }

        // Frequência
        if (isset($r['frequencia_minima'])) {
            $minimo = (float) $r['frequencia_minima']['percentagem'];
            if ($frequencia === null) {
                $j[] = 'Frequência não verificada: não existem registos de faltas para o aluno.';
            } elseif ($frequencia + 1e-9 < $minimo) {
                return $resultado('retido_faltas', [
                    'Frequência de '.$fmt($frequencia)."%, inferior ao mínimo exigido de {$fmt($minimo)}% das aulas.",
                ]);
            } else {
                $j[] = 'Frequência de '.$fmt($frequencia).'% (mínimo '.$fmt($minimo).'%).';
            }
        }

        $nota = (float) ($r['nota_minima_disciplina']['nota'] ?? $notaMinimaPadrao);
        $negativas = array_values(array_filter($disciplinas, fn ($d) => $d['classificacao'] + 1e-9 < $nota));
        $n = count($negativas);

        if ($n === 0) {
            $j[] = 'Classificação final igual ou superior a '.$fmt($nota).' em todas as '.count($disciplinas).' disciplinas.';
            if ($aplica('melhoria_nota')) {
                $limite = (float) $r['melhoria_nota']['nota_maxima'];
                $melhoria = array_filter($disciplinas, fn ($d) => $d['classificacao'] <= $limite + 1e-9);
                if ($melhoria) {
                    $j[] = 'Pode requerer exame de melhoria de nota em: '.$lista($melhoria).'.';
                }
            }

            return $resultado($positivo, $j);
        }

        $j[] = 'Classificação inferior a '.$fmt($nota).' em '.$n.' disciplina(s): '.$lista($negativas).'.';

        // Transição condicional
        if ($aplica('transicao_condicional')) {
            $p = $r['transicao_condicional'];
            $impedimentos = [];
            if ($n > (int) $p['max_disciplinas']) {
                $impedimentos[] = "excede o máximo de {$p['max_disciplinas']} disciplina(s)";
            }
            foreach ($negativas as $d) {
                if ($d['classificacao'] + 1e-9 < (float) $p['nota_minima']) {
                    $impedimentos[] = "{$d['nome']} com classificação inferior a ".$fmt((float) $p['nota_minima']);
                } elseif (! empty($p['excluir_nucleares']) && $d['nuclear']) {
                    $impedimentos[] = "{$d['nome']} é disciplina nuclear";
                } elseif (! empty($p['excluir_especificas']) && $d['especifica']) {
                    $impedimentos[] = "{$d['nome']} é disciplina específica da formação";
                }
            }
            if (! $impedimentos) {
                $j[] = 'Transição condicional: até '.$p['max_disciplinas'].' disciplina(s) com classificação entre '
                    .$fmt((float) $p['nota_minima']).' e '.$fmt((float) $p['nota_maxima']).', sem disciplinas nucleares ou específicas.';

                return $resultado('transita_condicionalmente', $j, $n);
            }
            $j[] = 'Transição condicional não aplicável: '.implode('; ', $impedimentos).'.';
        }

        // Exame de recurso
        if ($aplica('exame_recurso')) {
            $p = $r['exame_recurso'];
            $abaixo = array_filter($negativas, fn ($d) => $d['classificacao'] + 1e-9 < (float) $p['nota_minima']);
            if ($n <= (int) $p['max_disciplinas'] && ! $abaixo) {
                $j[] = 'Admitido a exame de recurso em: '.implode(', ', array_column($negativas, 'nome')).'.';

                return $resultado('admitido_recurso', $j, $n);
            }
            $j[] = 'Sem acesso a exame de recurso: '.($n > (int) $p['max_disciplinas']
                ? "mais de {$p['max_disciplinas']} disciplina(s) com classificação negativa"
                : 'classificação inferior a '.$fmt((float) $p['nota_minima']).' em '.implode(', ', array_column($abaixo, 'nome'))).'.';
        }

        if ($aplica('exame_extraordinario') && $n <= (int) $r['exame_extraordinario']['max_disciplinas']) {
            $j[] = 'Reúne as condições para requerer exame extraordinário.';
        }

        return $resultado($negativo, $j, $n);
    }

    /** Parâmetros com os valores por omissão do catálogo. */
    private function parametros(string $tipo, array $parametros): array
    {
        $definicao = config("avaliacao.regras.$tipo.parametros", []);
        $final = [];
        foreach ($definicao as $chave => $def) {
            $valor = $parametros[$chave] ?? $def['padrao'];
            $final[$chave] = match ($def['tipo']) {
                'numero' => (float) str_replace(',', '.', (string) $valor),
                'inteiro' => (int) $valor,
                'booleano' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
                'regimes' => array_values((array) $valor),
                default => $valor,
            };
        }

        return $final;
    }
}
