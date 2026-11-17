<?php

namespace App\Services\Avaliacao;

use App\Services\Avaliacao\Formula\ErroFormula;
use App\Services\Avaliacao\Formula\MotorFormulas;
use App\Services\Avaliacao\Formula\ValorEmFalta;

/**
 * Aplica uma definição de avaliação às notas de um aluno numa disciplina.
 *
 * Entrada ($notas):
 *   [1 => ['AC' => [12, 14], 'NPT' => 13], 2 => [...], 3 => [...], 4 => ['EN' => 11]]
 *   (períodos 1–3 = trimestres; 4 = exames)
 *
 * Ordem do cálculo:
 *   1. Em cada trimestre, as fórmulas trimestrais por ordem (ex.: MACT, MT).
 *      O resultado da fórmula X no trimestre t fica disponível como Xt (ex.: MT1, MACT3).
 *   2. As fórmulas anuais por ordem (ex.: MT3 = (MT1+MT2+MACT3)/3 ; MFED = 0,6×MT3 + 0,4×EN).
 * Valores em falta não são erro: as fórmulas dependentes ficam "incompletas".
 */
class MotorCalculo
{
    public function calcular(DefinicaoAvaliacao $d, array $notas): array
    {
        $casas = match ($d->arredondamento) {
            'unidade' => 0,
            'decimas' => 1,
            default => 2,
        };
        $vars = [];
        $erros = [];
        $porTrimestre = [];

        // Valores dos componentes: listas e valores simples
        $valoresComponentes = function (int $periodo) use ($d, $notas): array {
            $v = [];
            foreach ($d->componentes as $c) {
                $aplica = $c['momento'] === 'exame' ? $periodo === 4
                    : ($periodo <= 3 && DefinicaoAvaliacao::aplicaAoTrimestre($c['trimestres'], $periodo));
                if (! $aplica) {
                    continue;
                }
                $bruto = $notas[$periodo][$c['codigo']] ?? null;
                if ($c['tipo'] === 'lista') {
                    $v[$c['codigo']] = array_values(array_filter((array) ($bruto ?? []), fn ($x) => $x !== null && $x !== ''));
                } else {
                    $v[$c['codigo']] = is_array($bruto) ? ($bruto[0] ?? null) : $bruto;
                }
            }

            return $v;
        };

        $avaliar = function (array $f, array $contexto) use ($casas, &$erros): ?float {
            try {
                $r = MotorFormulas::avaliar($f['expressao'], $contexto);

                return $f['arredondar'] ? MotorFormulas::arredondar($r, $casas) : round($r, 2);
            } catch (ValorEmFalta) {
                return null;
            } catch (ErroFormula $e) {
                $erros[$f['codigo']] = $e->getMessage();

                return null;
            }
        };

        for ($t = 1; $t <= 3; $t++) {
            $contexto = $valoresComponentes($t);
            foreach ($contexto as $codigo => $valor) {
                if (! is_array($valor)) {
                    $vars[$codigo.$t] = $valor === null ? null : (float) $valor;
                }
            }
            $calculados = [];
            foreach ($d->formulas as $f) {
                if ($f['nivel'] !== 'trimestral' || ! DefinicaoAvaliacao::aplicaAoTrimestre($f['trimestres'], $t)) {
                    continue;
                }
                $valor = $avaliar($f, $contexto);
                $contexto[$f['codigo']] = $valor;
                $calculados[$f['codigo']] = $valor;
                $vars[$f['codigo'].$t] = $valor;
            }
            $porTrimestre[$t] = $calculados;
        }

        $exames = [];
        foreach ($valoresComponentes(4) as $codigo => $valor) {
            $vars[$codigo] = $valor;
            $exames[$codigo] = is_array($valor) ? $valor : ($valor === null ? null : (float) $valor);
        }
        $anuais = [];
        foreach ($d->formulas as $f) {
            if ($f['nivel'] !== 'anual') {
                continue;
            }
            $valor = $avaliar($f, $vars);
            $vars[$f['codigo']] = $valor;
            $anuais[$f['codigo']] = $valor;
        }

        $resultado = ['trimestres' => [], 'final' => null, 'variaveis' => $vars, 'erros' => $erros];
        for ($t = 1; $t <= 3; $t++) {
            $classificacao = $vars[$d->variavelTrimestral.$t] ?? null;
            $valores = $porTrimestre[$t];
            if (isset($anuais[$d->variavelTrimestral.$t])) {
                $valores[$d->variavelTrimestral] = $anuais[$d->variavelTrimestral.$t]; // ex.: MT3 calculada por fórmula anual
            }
            $resultado['trimestres'][$t] = $this->classificar($d, $classificacao) + ['valores' => $valores];
        }
        // Valores finais: componentes de exame (ex.: EN) e fórmulas anuais (ex.: MT3, MFED).
        $resultado['final'] = $this->classificar($d, $vars[$d->variavelFinal] ?? null) + ['valores' => $exames + $anuais];

        return $resultado;
    }

    /** Classificação, rótulo qualitativo e situação (positiva/negativa/incompleta). */
    public function classificar(DefinicaoAvaliacao $d, ?float $valor): array
    {
        if ($valor === null) {
            return ['classificacao' => null, 'qualitativa' => null, 'situacao' => 'incompleta'];
        }

        return [
            'classificacao' => $valor,
            'qualitativa' => $this->rotulo($d, $valor),
            'situacao' => $valor + 1e-9 >= $d->notaMinimaAprovacao ? 'positiva' : 'negativa',
        ];
    }

    public function rotulo(DefinicaoAvaliacao $d, float $valor): ?string
    {
        foreach ($d->intervalos as $i) {
            if ($valor >= $i['minimo'] - 1e-9) {
                return $i['rotulo'];
            }
        }

        return null;
    }
}
