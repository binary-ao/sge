<?php

namespace App\Services\Avaliacao;

use App\Models\ConfiguracaoAvaliacao;
use App\Models\EscalaClassificacao;
use App\Services\Avaliacao\Formula\MotorFormulas;

/**
 * Definição de cálculo independente da base de dados: pode ser construída a partir
 * de uma configuração guardada ou dos dados de um formulário (pré-visualização).
 */
class DefinicaoAvaliacao
{
    public function __construct(
        public array $componentes,          // [codigo, nome, tipo, momento, trimestres, nota_minima, nota_maxima, itens_previstos]
        public array $formulas,             // [codigo, nome, expressao, nivel, trimestres, arredondar]
        public array $regras,               // [tipo, parametros, activo]
        public array $intervalos,           // [minimo, maximo, rotulo, valor_referencia]
        public string $tipoClassificacao,
        public float $notaMinimaAprovacao,
        public string $arredondamento,
        public string $variavelTrimestral,
        public string $variavelFinal,
        public ?int $configuracaoId = null,
        public ?int $versao = null,
    ) {
        $up = fn ($s) => strtoupper(trim((string) $s));
        foreach ($this->componentes as &$c) {
            $c['codigo'] = $up($c['codigo']);
        }
        unset($c);
        foreach ($this->formulas as &$f) {
            $f['codigo'] = $up($f['codigo']);
        }
        unset($f);
        $this->variavelTrimestral = $up($this->variavelTrimestral);
        $this->variavelFinal = $up($this->variavelFinal);
        usort($this->intervalos, fn ($a, $b) => $b['minimo'] <=> $a['minimo']);
    }

    public static function deConfiguracao(ConfiguracaoAvaliacao $c): self
    {
        $c->loadMissing('componentes', 'formulas', 'regras', 'escala.intervalos');

        return new self(
            componentes: $c->componentes->map->only('codigo', 'nome', 'tipo', 'momento', 'trimestres', 'nota_minima', 'nota_maxima', 'itens_previstos')->all(),
            formulas: $c->formulas->map->only('codigo', 'nome', 'expressao', 'nivel', 'trimestres', 'arredondar')->all(),
            regras: $c->regras->where('activo', true)->map->only('tipo', 'parametros', 'activo')->values()->all(),
            intervalos: $c->escala->intervalos->map->only('minimo', 'maximo', 'rotulo', 'valor_referencia')->all(),
            tipoClassificacao: $c->tipo_classificacao,
            notaMinimaAprovacao: (float) $c->nota_minima_aprovacao,
            arredondamento: $c->arredondamento,
            variavelTrimestral: $c->variavel_trimestral,
            variavelFinal: $c->variavel_final,
            configuracaoId: $c->id,
            versao: $c->versao,
        );
    }

    /** A partir dos dados de um formulário (pré-visualização ou gravação). */
    public static function deArray(array $d, EscalaClassificacao $escala): self
    {
        $escala->loadMissing('intervalos');
        $num = fn ($v) => $v === null || $v === '' ? null : (float) str_replace(',', '.', (string) $v);

        return new self(
            componentes: array_values(array_map(fn ($c) => [
                'codigo' => $c['codigo'] ?? '', 'nome' => $c['nome'] ?? '', 'tipo' => $c['tipo'] ?? 'valor',
                'momento' => $c['momento'] ?? 'trimestral', 'trimestres' => self::normalizarTrimestres($c['trimestres'] ?? null),
                'nota_minima' => $num($c['nota_minima'] ?? 0) ?? 0.0,
                'nota_maxima' => $num($c['nota_maxima'] ?? null) ?? (float) $escala->nota_maxima,
                'itens_previstos' => max(1, (int) ($c['itens_previstos'] ?? 1)),
            ], $d['componentes'] ?? [])),
            formulas: array_values(array_map(fn ($f) => [
                'codigo' => $f['codigo'] ?? '', 'nome' => $f['nome'] ?? '', 'expressao' => (string) ($f['expressao'] ?? ''),
                'nivel' => $f['nivel'] ?? 'trimestral', 'trimestres' => self::normalizarTrimestres($f['trimestres'] ?? null),
                'arredondar' => ! empty($f['arredondar']),
            ], $d['formulas'] ?? [])),
            regras: array_values(array_filter(array_map(fn ($r) => [
                'tipo' => $r['tipo'] ?? '', 'parametros' => $r['parametros'] ?? [], 'activo' => ! empty($r['activo']),
            ], $d['regras'] ?? []), fn ($r) => $r['activo'])),
            intervalos: $escala->intervalos->map->only('minimo', 'maximo', 'rotulo', 'valor_referencia')->all(),
            tipoClassificacao: $d['tipo_classificacao'] ?? 'quantitativa',
            notaMinimaAprovacao: $num($d['nota_minima_aprovacao'] ?? 0) ?? 0.0,
            arredondamento: $d['arredondamento'] ?? 'unidade',
            variavelTrimestral: (string) ($d['variavel_trimestral'] ?? ''),
            variavelFinal: (string) ($d['variavel_final'] ?? ''),
        );
    }

    /** "1, 2" → "1,2"; todos ou nenhum → null (aplica-se a todos os trimestres). */
    public static function normalizarTrimestres(?string $t): ?string
    {
        $lista = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $t)), fn ($x) => in_array($x, ['1', '2', '3'], true))));
        sort($lista);

        return $lista && count($lista) < 3 ? implode(',', $lista) : null;
    }

    public static function aplicaAoTrimestre(?string $trimestres, int $t): bool
    {
        return $trimestres === null || $trimestres === '' || in_array((string) $t, explode(',', $trimestres), true);
    }

    public function componente(string $codigo): ?array
    {
        foreach ($this->componentes as $c) {
            if ($c['codigo'] === strtoupper($codigo)) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Valida a coerência da definição. Devolve erros no formato chave => mensagem
     * (chaves iguais aos nomes dos campos do formulário: formulas.2.expressao, etc.).
     */
    public function validar(): array
    {
        $erros = [];
        $codigos = [];
        $padrao = '/^[A-Z][A-Z0-9_]{0,19}$/';

        foreach ($this->componentes as $i => $c) {
            if (! preg_match($padrao, $c['codigo'])) {
                $erros["componentes.$i.codigo"] = 'Código inválido (letras, dígitos e _; deve começar por letra).';
            } elseif (isset($codigos[$c['codigo']])) {
                $erros["componentes.$i.codigo"] = "Código {$c['codigo']} repetido.";
            }
            $codigos[$c['codigo']] = true;
            if ($c['nota_maxima'] <= $c['nota_minima']) {
                $erros["componentes.$i.nota_maxima"] = 'A nota máxima deve ser superior à mínima.';
            }
        }
        if (! $this->componentes) {
            $erros['componentes'] = 'Defina pelo menos um componente de avaliação.';
        }

        // Variáveis disponíveis nas fórmulas trimestrais e anuais
        $trimestrais = [];
        $anuais = [];
        foreach ($this->componentes as $c) {
            if ($c['momento'] === 'trimestral') {
                $trimestrais[$c['codigo']] = $c['tipo'] === 'lista' ? 'lista' : 'valor';
                if ($c['tipo'] === 'valor') {
                    foreach ([1, 2, 3] as $t) {
                        $anuais[$c['codigo'].$t] = 'valor';
                    }
                }
            } else {
                $anuais[$c['codigo']] = $c['tipo'] === 'lista' ? 'lista' : 'valor';
            }
        }

        $codigosFormula = [];
        foreach ($this->formulas as $i => $f) {
            if (! preg_match($padrao, $f['codigo'])) {
                $erros["formulas.$i.codigo"] = 'Código inválido (letras, dígitos e _; deve começar por letra).';
            } elseif (isset($codigos[$f['codigo']]) || isset($codigosFormula[$f['codigo']])) {
                $erros["formulas.$i.codigo"] = "Código {$f['codigo']} já utilizado.";
            }
            $codigosFormula[$f['codigo']] = true;

            $problemas = MotorFormulas::validar($f['expressao'], $f['nivel'] === 'trimestral' ? $trimestrais : $anuais);
            if ($problemas) {
                $erros["formulas.$i.expressao"] = implode(' ', $problemas);
            }

            if ($f['nivel'] === 'trimestral') {
                $trimestrais[$f['codigo']] = 'valor';
                foreach ([1, 2, 3] as $t) {
                    if (self::aplicaAoTrimestre($f['trimestres'], $t)) {
                        $anuais[$f['codigo'].$t] = 'valor';
                    }
                }
            } else {
                $anuais[$f['codigo']] = 'valor';
            }
        }
        if (! $this->formulas) {
            $erros['formulas'] = 'Defina pelo menos uma fórmula.';
        }

        $codTrim = array_column(array_filter($this->formulas, fn ($f) => $f['nivel'] === 'trimestral'), 'codigo');
        $codAnual = array_column(array_filter($this->formulas, fn ($f) => $f['nivel'] === 'anual'), 'codigo');
        if (! in_array($this->variavelTrimestral, $codTrim, true)) {
            $erros['variavel_trimestral'] = 'Indique o código de uma fórmula trimestral (ex.: MT).';
        } else {
            // Cada trimestre tem classificação: pela fórmula trimestral ou por uma fórmula anual (ex.: MT3 nas classes de exame).
            foreach ([1, 2, 3] as $t) {
                if (! isset($anuais[$this->variavelTrimestral.$t])) {
                    $erros['variavel_trimestral'] = "Nenhuma fórmula produz a classificação do {$t}.º trimestre ({$this->variavelTrimestral}{$t}).";
                }
            }
        }
        if (! in_array($this->variavelFinal, $codAnual, true)) {
            $erros['variavel_final'] = 'Indique o código de uma fórmula anual (ex.: MFD).';
        }

        foreach ($this->regras as $i => $r) {
            if (! config("avaliacao.regras.{$r['tipo']}")) {
                $erros["regras.$i.tipo"] = 'Tipo de regra desconhecido.';
            }
        }

        return $erros;
    }
}
