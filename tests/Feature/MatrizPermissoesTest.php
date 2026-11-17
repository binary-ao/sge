<?php

namespace Tests\Feature;

use App\Models\Escola;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriz perfil × funcionalidade: verifica no servidor (não só na interface) o que cada
 * perfil pode abrir. 200 = permitido; 403 = proibido; 302 = exige escolher escola activa.
 */
class MatrizPermissoesTest extends TestCase
{
    private const ROTAS = [
        'paineis.provincial', 'paineis.municipal', 'paineis.escola', 'mapa', 'relatorios.index',
        'escolas.index', 'escolas.create', 'municipios.index', 'municipios.create', 'tipologias.index',
        'utilizadores.index', 'perfis.index', 'logs.index', 'copias.index',
        'cadastros.anos.index', 'academico.planos.create',
        'alunos.index', 'alunos.create', 'matriculas.create', 'turmas.index', 'turmas.create', 'professores.index', 'professores.create',
        'avaliacao.lancamento.index', 'avaliacao.pautas.index', 'avaliacao.periodos.index', 'avaliacao.configuracoes.index',
        'faltas.index', 'faltas.chamada', 'pagamentos.index', 'pagamentos.create', 'servicos.index', 'declaracoes.index',
        'cadastros.disciplinas.index', 'cadastros.niveis.index', 'cadastros.classes.index', 'cadastros.periodos.index',
    ];

    public static function perfis(): array
    {
        // Colunas pela ordem de ROTAS
        return [
            'Superutilizador (com escola activa)' => ['admin@sige.demo.ao', true, [
                200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200,
                200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200,
                200, 200, 200, 200]],
            'Direcção Provincial (numa escola)' => ['provincial@sige.demo.ao', true, [
                200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 403, 403, 403, 403, 200, 200,
                200, 403, 403, 200, 403, 200, 403, 200, 200, 403, 403, 200, 403, 403, 403, 403, 403,
                200, 200, 200, 200]],
            'Direcção Municipal (numa escola do município)' => ['municipal.dande@sige.demo.ao', true, [
                403, 200, 200, 200, 200, 200, 200, 200, 403, 403, 403, 403, 403, 403, 200, 403,
                200, 403, 403, 200, 403, 200, 403, 200, 200, 403, 403, 200, 403, 403, 403, 403, 403,
                200, 200, 200, 200]],
            'Director / Administrativo' => ['director@sige.demo.ao', false, [
                403, 403, 200, 403, 200, 200, 403, 403, 403, 403, 403, 403, 403, 403, 200, 200,
                200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 403, 200, 200, 200, 200, 200, 200,
                200, 200, 200, 200]],
            'Professor' => ['professor@sige.demo.ao', false, [
                403, 403, 200, 403, 403, 403, 403, 403, 403, 403, 403, 403, 403, 403, 200, 403,
                200, 403, 403, 200, 403, 403, 403, 200, 200, 403, 403, 200, 200, 403, 403, 403, 403,
                200, 200, 200, 200]],
            'Secretaria / Tesouraria' => ['secretaria@sige.demo.ao', false, [
                403, 403, 200, 403, 403, 403, 403, 403, 403, 403, 403, 403, 403, 403, 200, 403,
                200, 200, 200, 200, 403, 403, 403, 403, 403, 403, 403, 403, 403, 200, 200, 403, 200,
                200, 200, 200, 200]],
        ];
    }

    #[DataProvider('perfis')]
    public function test_matriz_de_permissoes(string $email, bool $entrarNumaEscola, array $esperado): void
    {
        $this->assertCount(count(self::ROTAS), $esperado);
        $this->entrarComo($email);
        if ($entrarNumaEscola) {
            $this->post(route('contexto.definir'), ['escola_id' => Escola::where('codigo', 'BGO-DAN-0205')->value('id')]);
        }

        $diferencas = [];
        foreach (self::ROTAS as $i => $rota) {
            $obtido = $this->get(route($rota))->getStatusCode();
            if ($obtido !== $esperado[$i]) {
                $diferencas[] = "{$rota}: esperado {$esperado[$i]}, obtido {$obtido}";
            }
        }
        $this->assertSame([], $diferencas, "Diferenças para {$email}");
    }

    public function test_sem_escola_activa_os_modulos_operacionais_pedem_escolha(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        foreach (['alunos.index', 'turmas.index', 'avaliacao.pautas.index', 'faltas.index', 'paineis.escola'] as $rota) {
            $this->get(route($rota))->assertRedirect(route('contexto.escolher'));
        }
    }
}
