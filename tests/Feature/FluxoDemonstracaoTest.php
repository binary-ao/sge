<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Disciplina;
use App\Models\ResultadoAvaliacao;
use App\Models\ServicoPagavel;
use App\Models\TipologiaEscola;
use App\Models\Turma;
use App\Support\ContextoEscola;
use Tests\TestCase;

/**
 * Sequência de demonstração da especificação (secção 21), de ponta a ponta:
 * os dados registados na escola alimentam os indicadores consultados pela Direcção Provincial.
 */
class FluxoDemonstracaoTest extends TestCase
{
    public function test_sequencia_completa_de_demonstracao(): void
    {
        $ano = AnoLectivo::where('actual', true)->first();

        // 1–2. Direcção Provincial: painel provincial
        $this->post(route('login'), ['email' => 'provincial@sige.demo.ao', 'password' => 'Bengo@2026'])->assertRedirect();
        $antes = null;
        $this->get(route('paineis.provincial'))->assertOk()->assertViewHas('totais', function ($t) use (&$antes) {
            $antes = $t;

            return true;
        });

        // 3. Distribuição por tipologia → clique abre as escolas dessa tipologia
        $ce = TipologiaEscola::where('sigla', 'CE')->first();
        $this->get(route('escolas.index', ['tipologia_id' => $ce->id]))->assertOk()->assertSee('Complexo Escolar n.º 205')->assertSee('3 instituições');

        // 4. Filtrar por município
        $this->get(route('paineis.provincial', ['municipio_id' => $this->escola('BGO-DAN-0205')->municipio_id]))->assertOk()
            ->assertViewHas('totais', fn ($t) => $t['escolas'] === 4);

        // 5. Níveis de ensino ministrados
        $this->get(route('escolas.show', $this->escola('BGO-DAN-0205')))->assertOk()
            ->assertSee('Ensino Primário')->assertSee('I Ciclo do Ensino Secundário')->assertSee('II Ciclo do Ensino Secundário Geral');
        $this->post(route('logout'));

        // 6–9. Área da escola: cadastrar aluno, matricular e colocar na turma
        $this->post(route('login'), ['email' => 'director@sige.demo.ao', 'password' => 'Bengo@2026']);
        $turma = Turma::where('ano_lectivo_id', $ano->id)->whereHas('classe', fn ($q) => $q->where('numero', 7))->firstOrFail();
        $this->post(route('alunos.store'), [
            'nome' => 'Lukeny Domingos Kiala', 'genero' => 'M', 'data_nascimento' => now()->subYears(12)->toDateString(),
            'nacionalidade' => 'Angolana', 'naturalidade' => 'Caxito', 'accao' => 'matricular',
            'encarregado' => ['nome' => 'Domingos Kiala', 'parentesco' => 'Pai', 'telefone' => '+244 923 456 789'],
        ])->assertRedirect();
        $aluno = Aluno::where('nome', 'Lukeny Domingos Kiala')->firstOrFail();
        $this->post(route('matriculas.store'), [
            'aluno_id' => $aluno->id, 'ano_lectivo_id' => $ano->id, 'classe_id' => $turma->classe_id, 'turma_id' => $turma->id,
            'tipo' => 'nova', 'data_matricula' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $matricula = $aluno->matriculas()->firstOrFail();
        $this->assertSame($turma->id, $matricula->turma_id);
        $this->get(route('turmas.show', $turma))->assertOk()->assertSee('Lukeny Domingos Kiala');

        // Pagamento da inscrição e declaração de matrícula (Fase 6)
        $inscricao = ServicoPagavel::where('tipo', 'matricula')->first();
        $this->post(route('pagamentos.store'), ['aluno_id' => $aluno->id, 'servicos' => [$inscricao->id], 'forma_pagamento' => 'numerario', 'data' => now()->toDateString()])
            ->assertRedirect();
        $this->post(route('declaracoes.store'), ['aluno_id' => $aluno->id, 'matricula_id' => $matricula->id, 'tipo' => 'matricula'])->assertRedirect();
        $this->post(route('logout'));

        // 10–11. Professor lança avaliações; médias calculadas automaticamente
        $this->post(route('login'), ['email' => 'professor@sige.demo.ao', 'password' => 'Bengo@2026']);
        $mat = Disciplina::withoutGlobalScope('escola')->whereNull('escola_id')->where('sigla', 'MAT')->first();
        $this->post(route('avaliacao.lancamento.guardar', [$turma, $mat->id]), [
            'periodo' => 1, 'notas' => [$matricula->id => ['AC' => [1 => '14', 2 => '16'], 'NPT' => [1 => '15']]],
        ])->assertSessionHasNoErrors();
        $r = ResultadoAvaliacao::semFiltroEscola()->where('matricula_id', $matricula->id)->where('disciplina_id', $mat->id)->where('periodo', 1)->first();
        $this->assertEquals(15, $r->valores['MACT']);
        $this->assertEquals(15, $r->classificacao);
        $this->post(route('logout'));

        // 12. Gerar a pauta
        $this->post(route('login'), ['email' => 'director@sige.demo.ao', 'password' => 'Bengo@2026']);
        $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 1]))->assertOk()->assertSee('Lukeny Domingos Kiala');
        $pdf = $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 1, 'formato' => 'pdf']));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->post(route('logout'));

        // 13–14. Regressar ao painel provincial: o aluno já conta nos indicadores consolidados
        $this->post(route('login'), ['email' => 'provincial@sige.demo.ao', 'password' => 'Bengo@2026']);
        app(ContextoEscola::class)->limparCache();
        $this->get(route('paineis.provincial'))->assertOk()
            ->assertViewHas('totais', fn ($t) => $t['alunos'] === $antes['alunos'] + 1 && $t['escolas'] === $antes['escolas']);

        // A operação ficou registada no histórico, com o autor de cada acção
        $this->assertDatabaseHas('logs_actividade', ['modelo_tipo' => 'Aluno', 'modelo_id' => $aluno->id, 'accao' => 'criar']);
        $this->assertDatabaseHas('logs_actividade', ['modelo_tipo' => 'Turma', 'modelo_id' => $turma->id, 'accao' => 'actualizar']);
    }
}
