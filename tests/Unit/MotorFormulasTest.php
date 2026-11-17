<?php

namespace Tests\Unit;

use App\Services\Avaliacao\Formula\ErroFormula;
use App\Services\Avaliacao\Formula\MotorFormulas as F;
use App\Services\Avaliacao\Formula\ValorEmFalta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MotorFormulasTest extends TestCase
{
    public function test_formulas_do_anexo_iii(): void
    {
        // A. MAC = média das avaliações contínuas
        $this->assertEqualsWithDelta(12.0, F::avaliar('MEDIA(AC)', ['AC' => [10, 12, 14]]), 1e-9);
        // B. MT = (MACr + NPT) / 2
        $this->assertEqualsWithDelta(12.5, F::avaliar('(MACT + NPT) / 2', ['MACT' => 12, 'NPT' => 13]), 1e-9);
        // C. MFD = (MT1 + MT2 + MT3) / 3
        $this->assertEqualsWithDelta(12.0, F::avaliar('(MT1 + MT2 + MT3) / 3', ['MT1' => 11, 'MT2' => 12, 'MT3' => 13]), 1e-9);
        // E/F. Exame combinado e média final com vírgula decimal e símbolo ×
        $mec = F::avaliar('(NEE + NEO) / 2', ['NEE' => 10, 'NEO' => 14]);
        $this->assertEqualsWithDelta(11.4, F::avaliar('0,6 × MACA + 0,4 × MEC', ['MACA' => 11, 'MEC' => $mec]), 1e-9);
        // G. MT3 em classes de Exame Nacional
        $this->assertEqualsWithDelta(12.0, F::avaliar('(MT1 + MT2 + MACT3) / 3', ['MT1' => 12, 'MT2' => 11, 'MACT3' => 13]), 1e-9);
        // H. 12.ª classe
        $this->assertEqualsWithDelta(11.5, F::avaliar('0,5 * MT3 + 0,5 * EN', ['MT3' => 12, 'EN' => 11]), 1e-9);
    }

    public function test_precedencia_parenteses_e_funcoes(): void
    {
        $this->assertEqualsWithDelta(14.0, F::avaliar('2 + 3 * 4', []), 1e-9);
        $this->assertEqualsWithDelta(20.0, F::avaliar('(2 + 3) * 4', []), 1e-9);
        $this->assertEqualsWithDelta(-8.0, F::avaliar('-2 ^ 3', []), 1e-9);
        $this->assertEqualsWithDelta(512.0, F::avaliar('2 ^ 3 ^ 2', []), 1e-9);
        $this->assertEqualsWithDelta(10.0, F::avaliar('ARRED(9,5)', []), 1e-9);
        $this->assertEqualsWithDelta(9.0, F::avaliar('ARRED(9,49)', []), 1e-9);
        $this->assertEqualsWithDelta(12.35, F::avaliar('ARRED(12,345; 2)', []), 1e-9);
        $this->assertEqualsWithDelta(7.0, F::avaliar('SE(NOTA >= 10; 10; 7)', ['NOTA' => 9]), 1e-9);
        $this->assertEqualsWithDelta(1.0, F::avaliar('E(A > 1; B < 5)', ['A' => 2, 'B' => 3]), 1e-9);
        $this->assertEqualsWithDelta(16.0, F::avaliar('MAX(AC; NPT)', ['AC' => [10, 14], 'NPT' => 16]), 1e-9);
        $this->assertEqualsWithDelta(3.0, F::avaliar('CONTAR(AC)', ['ac' => [1, null, 2, 3]]), 1e-9); // nulos ignorados; nomes sem distinção de maiúsculas
    }

    public function test_valores_em_falta_sao_sinalizados(): void
    {
        $this->expectException(ValorEmFalta::class);
        F::avaliar('(MACT + NPT) / 2', ['MACT' => 12, 'NPT' => null]);
    }

    public function test_lista_vazia_e_valor_em_falta(): void
    {
        $this->expectException(ValorEmFalta::class);
        F::avaliar('MEDIA(AC)', ['AC' => []]);
    }

    public function test_variaveis_e_validacao(): void
    {
        $this->assertSame(['MT1', 'MT2', 'MACT3'], F::variaveis('(MT1 + MT2 + MACT3) / 3'));
        $this->assertSame([], F::validar('MEDIA(AC)', ['AC' => 'lista']));
        $this->assertSame(['Variável desconhecida: XPTO.'], F::validar('MEDIA(AC) + XPTO', ['AC' => 'lista']));
        $this->assertNotEmpty(F::validar('AC + 1', ['AC' => 'lista']));          // lista fora de agregadora
        $this->assertNotEmpty(F::validar('ARRED(AC)', ['AC' => 'lista']));
        $this->assertNotEmpty(F::validar('(MT1 + MT2', ['MT1' => 'valor', 'MT2' => 'valor']));
    }

    public function test_divisao_por_zero(): void
    {
        $this->expectException(ErroFormula::class);
        F::avaliar('10 / (A - A)', ['A' => 3]);
    }

    public static function expressoesMaliciosas(): array
    {
        return [
            ['system("dir")'],
            ['phpinfo()'],
            ['$x = 1'],
            ['`ls`'],
            ['1; DROP TABLE notas'],
            ['exec("rm")'],
            ['AC[0]'],
            ['"texto"'],
            ['1 + '],
            [str_repeat('(', 60).'1'.str_repeat(')', 60)],
        ];
    }

    #[DataProvider('expressoesMaliciosas')]
    public function test_codigo_arbitrario_e_rejeitado(string $expr): void
    {
        $this->expectException(ErroFormula::class);
        F::avaliar($expr, ['AC' => [1]]);
    }
}
