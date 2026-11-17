<?php

namespace App\Services\Avaliacao\Formula;

/**
 * Interpretador seguro de fórmulas de avaliação.
 *
 * NÃO utiliza eval() nem executa código: a expressão é decomposta em símbolos
 * (tokenizer), convertida numa árvore sintáctica por um parser recursivo
 * descendente e avaliada sobre um conjunto fechado de operadores e funções.
 *
 * Sintaxe:
 *   números ........ 10   7,5   0.6        (vírgula ou ponto decimal)
 *   variáveis ...... AC  NPT  MT1  MACT3   (letras, dígitos e _; sem distinção de maiúsculas)
 *   operadores ..... +  -  *  /  ^  (também × e ÷)
 *   comparações .... <  <=  >  >=  =  <>  (devolvem 1 ou 0)
 *   funções ........ MEDIA, SOMA, CONTAR, MIN, MAX, ARRED, TRUNCAR, ABS, SE, E, OU
 *   separador ...... ;   (porque a vírgula é o separador decimal)
 *
 * Variáveis do tipo lista (ex.: AC = avaliações contínuas) só podem ser usadas
 * dentro de funções de agregação: MEDIA(AC), SOMA(AC), CONTAR(AC), MIN(AC), MAX(AC).
 */
class MotorFormulas
{
    public const FUNCOES = [
        'MEDIA' => [1, null, 'Média aritmética dos argumentos (aceita listas).'],
        'SOMA' => [1, null, 'Soma dos argumentos (aceita listas).'],
        'CONTAR' => [1, null, 'Número de valores (aceita listas).'],
        'MIN' => [1, null, 'Menor valor (aceita listas).'],
        'MAX' => [1, null, 'Maior valor (aceita listas).'],
        'ARRED' => [1, 2, 'Arredonda (meio para cima). ARRED(x; casas).'],
        'TRUNCAR' => [1, 2, 'Trunca as casas decimais. TRUNCAR(x; casas).'],
        'ABS' => [1, 1, 'Valor absoluto.'],
        'SE' => [3, 3, 'SE(condição; valor se verdadeiro; valor se falso).'],
        'E' => [2, null, 'Verdadeiro (1) se todos os argumentos forem verdadeiros.'],
        'OU' => [2, null, 'Verdadeiro (1) se algum argumento for verdadeiro.'],
    ];

    private const AGREGADORAS = ['MEDIA', 'SOMA', 'CONTAR', 'MIN', 'MAX'];

    private const LIMITE_COMPRIMENTO = 500;

    /** Cache de árvores já compiladas (por expressão). */
    private static array $cache = [];

    /* =========================================================== API */

    /** Compila a expressão e devolve a árvore sintáctica. Lança ErroFormula. */
    public static function compilar(string $expressao): array
    {
        $expressao = trim($expressao);
        if (isset(self::$cache[$expressao])) {
            return self::$cache[$expressao];
        }
        if ($expressao === '') {
            throw new ErroFormula('A fórmula está vazia.');
        }
        if (mb_strlen($expressao) > self::LIMITE_COMPRIMENTO) {
            throw new ErroFormula('A fórmula é demasiado longa.');
        }

        $parser = new self();
        $parser->simbolos = self::tokenizar($expressao);
        $parser->pos = 0;
        $arvore = $parser->expressao();
        if ($parser->actual()['t'] !== 'fim') {
            throw new ErroFormula('Símbolo inesperado "'.$parser->actual()['v'].'"', $parser->actual()['p']);
        }

        return self::$cache[$expressao] = $arvore;
    }

    /** Variáveis referidas na fórmula (em maiúsculas, sem repetições). */
    public static function variaveis(array|string $formula): array
    {
        $arvore = is_string($formula) ? self::compilar($formula) : $formula;
        $lista = [];
        self::recolherVariaveis($arvore, $lista);

        return array_values(array_unique($lista));
    }

    /**
     * Avalia a fórmula. $valores: nome => número | null | lista de números.
     * Lança ValorEmFalta se faltar um valor; ErroFormula se o cálculo for impossível.
     */
    public static function avaliar(array|string $formula, array $valores): float
    {
        $arvore = is_string($formula) ? self::compilar($formula) : $formula;
        $valores = array_change_key_case($valores, CASE_UPPER);
        $r = self::valor($arvore, $valores);

        if (is_array($r)) {
            throw new ErroFormula('O resultado da fórmula é uma lista. Use uma função como MEDIA() ou SOMA().');
        }
        if (! is_finite($r)) {
            throw new ErroFormula('O resultado não é um número válido.');
        }

        return $r;
    }

    /**
     * Valida a fórmula contra as variáveis disponíveis. Devolve a lista de erros (vazia = válida).
     * $disponiveis: nome => 'valor' | 'lista'
     */
    public static function validar(string $expressao, array $disponiveis): array
    {
        try {
            $arvore = self::compilar($expressao);
        } catch (ErroFormula $e) {
            return [$e->getMessage()];
        }
        $disponiveis = array_change_key_case($disponiveis, CASE_UPPER);
        $erros = [];
        foreach (self::variaveis($arvore) as $v) {
            if (! isset($disponiveis[$v])) {
                $erros[] = "Variável desconhecida: {$v}.";
            }
        }
        self::verificarListas($arvore, $disponiveis, $erros, false);

        return array_values(array_unique($erros));
    }

    /** Arredondamento "meio para cima" (ex.: 9,5 → 10). */
    public static function arredondar(float $valor, int $casas = 0): float
    {
        $f = 10 ** $casas;

        return floor($valor * $f + 0.5 + 1e-9) / $f;
    }

    /* ===================================================== Tokenizer */

    private static function tokenizar(string $s): array
    {
        $s = str_replace(['×', '÷', '−', '≤', '≥', '≠'], ['*', '/', '-', '<=', '>=', '<>'], $s);
        $simbolos = [];
        $i = 0;
        $n = strlen($s);

        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            if (ctype_digit($c) || (($c === '.' || $c === ',') && $i + 1 < $n && ctype_digit($s[$i + 1]))) {
                $ini = $i;
                $num = '';
                $separador = false;
                while ($i < $n && (ctype_digit($s[$i]) || (! $separador && ($s[$i] === '.' || $s[$i] === ',') && $i + 1 < $n && ctype_digit($s[$i + 1])))) {
                    if ($s[$i] === '.' || $s[$i] === ',') {
                        $separador = true;
                        $num .= '.';
                    } else {
                        $num .= $s[$i];
                    }
                    $i++;
                }
                $simbolos[] = ['t' => 'num', 'v' => (float) $num, 'p' => $ini + 1];

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $ini = $i;
                while ($i < $n && (ctype_alnum($s[$i]) || $s[$i] === '_')) {
                    $i++;
                }
                $simbolos[] = ['t' => 'id', 'v' => strtoupper(substr($s, $ini, $i - $ini)), 'p' => $ini + 1];

                continue;
            }
            $dois = substr($s, $i, 2);
            if (in_array($dois, ['<=', '>=', '<>', '=='], true)) {
                $simbolos[] = ['t' => 'op', 'v' => $dois === '==' ? '=' : $dois, 'p' => $i + 1];
                $i += 2;

                continue;
            }
            if (str_contains('+-*/^()<>=;', $c)) {
                $simbolos[] = ['t' => $c === '(' || $c === ')' || $c === ';' ? $c : 'op', 'v' => $c, 'p' => $i + 1];
                $i++;

                continue;
            }
            throw new ErroFormula('Carácter não permitido "'.mb_substr(substr($s, $i), 0, 1).'"', $i + 1);
        }
        $simbolos[] = ['t' => 'fim', 'v' => 'fim da fórmula', 'p' => $n + 1];

        return $simbolos;
    }

    /* ======================================================== Parser */

    private array $simbolos = [];
    private int $pos = 0;
    private int $profundidade = 0;

    private function actual(): array
    {
        return $this->simbolos[$this->pos];
    }

    private function consumir(): array
    {
        return $this->simbolos[$this->pos++];
    }

    private function eOp(string ...$ops): bool
    {
        $a = $this->actual();

        return $a['t'] === 'op' && in_array($a['v'], $ops, true);
    }

    // comparacao := soma ( ( < | <= | > | >= | = | <> ) soma )?
    private function expressao(): array
    {
        if (++$this->profundidade > 50) {
            throw new ErroFormula('A fórmula tem demasiados níveis de parênteses.');
        }
        $esq = $this->soma();
        if ($this->eOp('<', '<=', '>', '>=', '=', '<>')) {
            $op = $this->consumir()['v'];
            $esq = ['b', $op, $esq, $this->soma()];
        }
        $this->profundidade--;

        return $esq;
    }

    private function soma(): array
    {
        $esq = $this->produto();
        while ($this->eOp('+', '-')) {
            $op = $this->consumir()['v'];
            $esq = ['b', $op, $esq, $this->produto()];
        }

        return $esq;
    }

    private function produto(): array
    {
        $esq = $this->unario();
        while ($this->eOp('*', '/')) {
            $op = $this->consumir()['v'];
            $esq = ['b', $op, $esq, $this->unario()];
        }

        return $esq;
    }

    private function unario(): array
    {
        if ($this->eOp('-', '+')) {
            $op = $this->consumir()['v'];
            $no = $this->unario();

            return $op === '-' ? ['u', '-', $no] : $no;
        }

        return $this->potencia();
    }

    private function potencia(): array
    {
        $base = $this->primario();
        if ($this->eOp('^')) {
            $this->consumir();

            return ['b', '^', $base, $this->unario()]; // associativa à direita
        }

        return $base;
    }

    private function primario(): array
    {
        $s = $this->actual();

        if ($s['t'] === 'num') {
            $this->consumir();

            return ['n', $s['v']];
        }
        if ($s['t'] === '(') {
            $this->consumir();
            $no = $this->expressao();
            $this->esperar(')');

            return $no;
        }
        if ($s['t'] === 'id') {
            $this->consumir();
            if ($this->actual()['t'] === '(') {
                return $this->funcao($s);
            }

            return ['v', $s['v']];
        }
        if ($s['t'] === 'fim') {
            throw new ErroFormula('A fórmula termina de forma incompleta', $s['p']);
        }
        throw new ErroFormula('Símbolo inesperado "'.$s['v'].'"', $s['p']);
    }

    private function funcao(array $s): array
    {
        $nome = $s['v'];
        if (! isset(self::FUNCOES[$nome])) {
            throw new ErroFormula("Função desconhecida: {$nome}", $s['p']);
        }
        $this->esperar('(');
        $args = [];
        if ($this->actual()['t'] !== ')') {
            $args[] = $this->expressao();
            while ($this->actual()['t'] === ';') {
                $this->consumir();
                $args[] = $this->expressao();
            }
        }
        $this->esperar(')');

        [$min, $max] = self::FUNCOES[$nome];
        if (count($args) < $min || ($max !== null && count($args) > $max)) {
            $esperado = $max === null ? "pelo menos {$min}" : ($min === $max ? (string) $min : "entre {$min} e {$max}");
            throw new ErroFormula("A função {$nome} recebe {$esperado} argumento(s); recebeu ".count($args), $s['p']);
        }

        return ['f', $nome, $args];
    }

    private function esperar(string $tipo): void
    {
        $s = $this->actual();
        if ($s['t'] !== $tipo) {
            $mensagem = $tipo === ')' ? 'Falta fechar um parêntese' : "Esperava \"{$tipo}\"";
            throw new ErroFormula($mensagem, $s['p']);
        }
        $this->consumir();
    }

    /* ===================================================== Avaliação */

    private static function valor(array $no, array $vars): float|array
    {
        switch ($no[0]) {
            case 'n':
                return $no[1];

            case 'v':
                if (! array_key_exists($no[1], $vars)) {
                    throw new ValorEmFalta($no[1]);
                }
                $v = $vars[$no[1]];
                if ($v === null || $v === '') {
                    throw new ValorEmFalta($no[1]);
                }
                if (is_array($v)) {
                    return array_values(array_map('floatval', array_filter($v, fn ($x) => $x !== null && $x !== '')));
                }

                return (float) $v;

            case 'u':
                return -self::numero(self::valor($no[2], $vars));

            case 'b':
                $a = self::numero(self::valor($no[2], $vars));
                $b = self::numero(self::valor($no[3], $vars));

                return match ($no[1]) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0.0 ? throw new ErroFormula('Divisão por zero.') : $a / $b,
                    '^' => $a ** $b,
                    '<' => (float) ($a < $b),
                    '<=' => (float) ($a <= $b),
                    '>' => (float) ($a > $b),
                    '>=' => (float) ($a >= $b),
                    '=' => (float) (abs($a - $b) < 1e-9),
                    '<>' => (float) (abs($a - $b) >= 1e-9),
                };

            case 'f':
                return self::chamar($no[1], $no[2], $vars);
        }
        throw new ErroFormula('Expressão inválida.');
    }

    private static function chamar(string $nome, array $args, array $vars): float
    {
        if ($nome === 'SE') {
            // Avaliação preguiçosa: só calcula o ramo escolhido.
            return self::numero(self::valor($args[0], $vars)) != 0.0
                ? self::numero(self::valor($args[1], $vars))
                : self::numero(self::valor($args[2], $vars));
        }

        if (in_array($nome, self::AGREGADORAS, true)) {
            $valores = [];
            foreach ($args as $a) {
                $v = self::valor($a, $vars);
                is_array($v) ? array_push($valores, ...$v) : $valores[] = $v;
            }
            if ($nome === 'CONTAR') {
                return (float) count($valores);
            }
            if (! $valores) {
                // Lista vazia: ainda não há avaliações lançadas.
                throw new ValorEmFalta(self::nomeDaLista($args));
            }

            return match ($nome) {
                'MEDIA' => array_sum($valores) / count($valores),
                'SOMA' => array_sum($valores),
                'MIN' => min($valores),
                'MAX' => max($valores),
            };
        }

        $n = array_map(fn ($a) => self::numero(self::valor($a, $vars)), $args);

        return match ($nome) {
            'ARRED' => self::arredondar($n[0], (int) ($n[1] ?? 0)),
            'TRUNCAR' => (fn ($f) => ($n[0] >= 0 ? floor($n[0] * $f) : ceil($n[0] * $f)) / $f)(10 ** (int) ($n[1] ?? 0)),
            'ABS' => abs($n[0]),
            'E' => (float) ! in_array(0.0, $n, true),
            'OU' => (float) (count(array_filter($n, fn ($x) => $x != 0.0)) > 0),
        };
    }

    private static function numero(float|array $v): float
    {
        if (is_array($v)) {
            throw new ErroFormula('Uma lista de avaliações só pode ser usada dentro de MEDIA(), SOMA(), CONTAR(), MIN() ou MAX().');
        }

        return $v;
    }

    private static function nomeDaLista(array $args): string
    {
        foreach ($args as $a) {
            if ($a[0] === 'v') {
                return $a[1];
            }
        }

        return 'lista';
    }

    private static function recolherVariaveis(array $no, array &$lista): void
    {
        match ($no[0]) {
            'v' => $lista[] = $no[1],
            'u' => self::recolherVariaveis($no[2], $lista),
            'b' => [self::recolherVariaveis($no[2], $lista), self::recolherVariaveis($no[3], $lista)],
            'f' => array_map(fn ($a) => self::recolherVariaveis($a, $lista), $no[2]),
            default => null,
        };
    }

    /** Verifica que as listas só aparecem como argumento directo de funções agregadoras. */
    private static function verificarListas(array $no, array $disp, array &$erros, bool $dentroAgregadora): void
    {
        if ($no[0] === 'v') {
            if (($disp[$no[1]] ?? null) === 'lista' && ! $dentroAgregadora) {
                $erros[] = "{$no[1]} é uma lista de avaliações: use-a dentro de MEDIA(), SOMA(), CONTAR(), MIN() ou MAX().";
            }

            return;
        }
        if ($no[0] === 'u') {
            self::verificarListas($no[2], $disp, $erros, false);
        } elseif ($no[0] === 'b') {
            self::verificarListas($no[2], $disp, $erros, false);
            self::verificarListas($no[3], $disp, $erros, false);
        } elseif ($no[0] === 'f') {
            $agrega = in_array($no[1], self::AGREGADORAS, true);
            foreach ($no[2] as $a) {
                self::verificarListas($a, $disp, $erros, $agrega && $a[0] === 'v');
            }
        }
    }
}
