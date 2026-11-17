<?php

namespace Tests\Feature;

use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Classe;
use App\Models\ConfiguracaoAvaliacao;
use App\Models\Disciplina;
use App\Models\EscalaClassificacao;
use App\Models\HistoricoConfiguracao;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLancamento;
use App\Models\ResultadoAvaliacao;
use App\Models\ResultadoFinal;
use App\Models\Turma;
use App\Services\Avaliacao\MotorRegras;
use App\Services\Avaliacao\ServicoResultados;
use App\Support\ContextoEscola;
use Tests\TestCase;

class AvaliacaoTest extends TestCase
{
    private function ano(): AnoLectivo
    {
        return AnoLectivo::where('actual', true)->first();
    }

    /** Turma da 7.ª classe do Complexo n.º 205 (I Ciclo, transição, escala 0–20). */
    private function turma7(): Turma
    {
        return Turma::semFiltroEscola()->where('escola_id', $this->escola('BGO-DAN-0205')->id)->where('ano_lectivo_id', $this->ano()->id)
            ->whereHas('classe', fn ($q) => $q->where('numero', 7))->firstOrFail();
    }

    private function disciplina(string $sigla): Disciplina
    {
        return Disciplina::withoutGlobalScope('escola')->whereNull('escola_id')->where('sigla', $sigla)->firstOrFail();
    }

    /* ---------------------------------------------------------- Regras */

    public function test_motor_de_regras_do_ensino_secundario(): void
    {
        $motor = app(MotorRegras::class);
        $regras = ConfiguracaoAvaliacao::where('nome', 'like', 'I Ciclo — 7.ª%')->where('ano_lectivo_id', $this->ano()->id)->first()
            ->regras->map->only('tipo', 'parametros', 'activo')->all();
        $d = fn ($nome, $nota, $nuclear = false, $esp = false) => ['nome' => $nome, 'nuclear' => $nuclear, 'especifica' => $esp, 'classificacao' => $nota];

        $r = $motor->avaliar($regras, 'transicao', [$d('Matemática', 12, true), $d('História', 14)], null, 10);
        $this->assertSame('transita', $r['situacao']);

        $r = $motor->avaliar($regras, 'transicao', [$d('Matemática', 12, true), $d('História', 8), $d('Física', 9)], null, 10);
        $this->assertSame('transita_condicionalmente', $r['situacao']);
        $this->assertStringContainsString('História (8)', implode(' ', $r['justificacao']));

        $r = $motor->avaliar($regras, 'transicao', [$d('Matemática', 8, true), $d('História', 14)], null, 10);
        $this->assertSame('retido', $r['situacao']);
        $this->assertStringContainsString('Matemática é disciplina nuclear', implode(' ', $r['justificacao']));

        $r = $motor->avaliar($regras, 'transicao', [$d('História', 8), $d('Física', 9), $d('Química', 9)], null, 10);
        $this->assertSame('retido', $r['situacao']);
        $this->assertStringContainsString('excede o máximo de 2', implode(' ', $r['justificacao']));

        $r = $motor->avaliar($regras, 'transicao', [$d('História', 6), $d('Física', 14)], null, 10);
        $this->assertSame('retido', $r['situacao']); // abaixo de 7: sem transição condicional

        $r = $motor->avaliar($regras, 'transicao', [$d('História', 14)], 50.0, 10);
        $this->assertSame('retido_faltas', $r['situacao']);

        $r = $motor->avaliar($regras, 'transicao', [$d('História', null)], null, 10);
        $this->assertSame('incompleto', $r['situacao']);
    }

    public function test_motor_de_regras_exame_nacional_e_pre_escolar(): void
    {
        $motor = app(MotorRegras::class);
        $regras12 = ConfiguracaoAvaliacao::where('nome', 'like', '%12.ª classe%')->where('ano_lectivo_id', $this->ano()->id)->first()->regras->map->only('tipo', 'parametros', 'activo')->all();
        $d = fn ($nome, $nota) => ['nome' => $nome, 'nuclear' => false, 'especifica' => false, 'classificacao' => $nota];

        $r = $motor->avaliar($regras12, 'exame_nacional', [$d('Física', 8), $d('Química', 9), $d('Biologia', 15)], null, 10);
        $this->assertSame('admitido_recurso', $r['situacao']);

        $r = $motor->avaliar($regras12, 'exame_nacional', [$d('Física', 5), $d('Biologia', 15)], null, 10);
        $this->assertSame('reprovado', $r['situacao']);
        $this->assertStringContainsString('exame extraordinário', implode(' ', $r['justificacao']));

        $r = $motor->avaliar($regras12, 'exame_nacional', [$d('Física', 12), $d('Biologia', 17)], null, 10);
        $this->assertSame('aprovado', $r['situacao']);
        $this->assertStringContainsString('melhoria de nota em: Física', implode(' ', $r['justificacao']));

        $pre = [['tipo' => 'progressao_automatica', 'parametros' => [], 'activo' => true]];
        $this->assertSame('progressao_automatica', $motor->avaliar($pre, 'sem_retencao', [$d('Comunicação', 2)], null, 5)['situacao']);
    }

    /* ------------------------------------------------- Dados semeados */

    public function test_resultados_semeados_coincidem_com_o_motor(): void
    {
        $anterior = AnoLectivo::where('designacao', '2025/2026')->first();
        $servico = app(ServicoResultados::class);
        $amostra = ResultadoAvaliacao::semFiltroEscola()->where('periodo', 0)
            ->whereHas('matricula', fn ($q) => $q->where('ano_lectivo_id', $anterior->id))->inRandomOrder()->limit(25)->get();
        $this->assertCount(25, $amostra);

        foreach ($amostra as $r) {
            $m = Matricula::semFiltroEscola()->with('classe')->find($r->matricula_id);
            $calculo = $servico->recalcularDisciplina($m, $r->disciplina_id, null, false);
            $this->assertEquals($r->classificacao, $calculo['final']['classificacao'], "Matrícula {$m->id}, disciplina {$r->disciplina_id}");
        }
        // Exame nacional da 9.ª: MFED = 0,6 × MT3 + 0,4 × EN (arredondado)
        $r9 = ResultadoAvaliacao::semFiltroEscola()->where('periodo', 0)->whereHas('matricula', fn ($q) => $q->where('ano_lectivo_id', $anterior->id)
            ->whereHas('classe', fn ($c) => $c->where('numero', 9)))->whereNotNull('classificacao')->first();
        $v = $r9->valores;
        $this->assertEquals(round(0.6 * $v['MT3'] + 0.4 * $v['EN']), $r9->classificacao);
    }

    /* ------------------------------------------------------ Lançamento */

    public function test_professor_lanca_notas_e_medias_sao_calculadas(): void
    {
        $this->entrarComo('professor@sige.demo.ao');
        $turma = $this->turma7();
        $mat = $this->disciplina('MAT');
        $m = $turma->matriculasActivas()->first();

        $this->get(route('avaliacao.lancamento.grelha', [$turma, $mat->id, 'periodo' => 1]))->assertOk()->assertSee('Gravar e calcular');

        $this->post(route('avaliacao.lancamento.guardar', [$turma, $mat->id]), [
            'periodo' => 1,
            'notas' => [$m->id => ['AC' => [1 => '12', 2 => '14', 3 => ''], 'NPT' => [1 => '13,5']]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $r = ResultadoAvaliacao::where('matricula_id', $m->id)->where('disciplina_id', $mat->id)->where('periodo', 1)->firstOrFail();
        $this->assertEquals(13, $r->valores['MACT']);        // (12 + 14) / 2; a AC3 vazia é ignorada
        $this->assertEquals(13, $r->classificacao);          // MT = (13 + 13,5) / 2 = 13,25 → 13
        $this->assertSame('positiva', $r->situacao);
        $this->assertNotNull($r->configuracao_versao);
        $this->assertDatabaseHas('logs_actividade', ['modelo_tipo' => 'Turma', 'modelo_id' => $turma->id]);
    }

    public function test_professor_so_lanca_nas_disciplinas_atribuidas(): void
    {
        $turma = $this->turma7();
        $lp = $this->disciplina('LP');
        $this->entrarComo('professor@sige.demo.ao');
        $prof = app(ContextoEscola::class)->professorActual();
        $this->assertFalse(AtribuicaoProfessor::where('turma_id', $turma->id)->where('disciplina_id', $lp->id)->where('professor_id', $prof->id)->exists());

        $this->get(route('avaliacao.lancamento.grelha', [$turma, $lp->id]))->assertNotFound();
        $this->post(route('avaliacao.lancamento.guardar', [$turma, $lp->id]), ['periodo' => 1, 'notas' => []])->assertNotFound();

        $this->entrarComo('director@sige.demo.ao');
        $this->get(route('avaliacao.lancamento.grelha', [$turma, $lp->id]))->assertOk();
    }

    public function test_notas_fora_da_escala_sao_rejeitadas_sem_gravar_nada(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $turma = $this->turma7();
        $hist = $this->disciplina('HIST');
        [$a, $b] = $turma->matriculasActivas()->take(2)->get()->all();
        $antes = Nota::count();

        $this->post(route('avaliacao.lancamento.guardar', [$turma, $hist->id]), [
            'periodo' => 1,
            'notas' => [$a->id => ['AC' => [1 => '15']], $b->id => ['AC' => [1 => '25']]],
        ])->assertSessionHasErrors("notas.{$b->id}.AC.1");
        $this->assertSame($antes, Nota::count());

        // Aluno de outra turma
        $outra = Matricula::where('estado', 'activa')->where('turma_id', '!=', $turma->id)->first();
        $this->post(route('avaliacao.lancamento.guardar', [$turma, $hist->id]), ['periodo' => 1, 'notas' => [$outra->id => ['AC' => [1 => '10']]]])
            ->assertSessionHasErrors('notas');
    }

    public function test_fecho_bloqueia_e_reabertura_exige_motivo(): void
    {
        $turma = $this->turma7();
        $mat = $this->disciplina('MAT');
        $m = $turma->matriculasActivas()->first();
        $ano = $this->ano();

        $this->entrarComo('director@sige.demo.ao');
        $this->post(route('avaliacao.periodos.fechar'), ['ano_lectivo_id' => $ano->id, 'periodo' => 1])->assertSessionHas('sucesso');
        $this->assertTrue(PeriodoLancamento::estaFechado($turma->escola_id, $ano->id, 1));

        $this->entrarComo('professor@sige.demo.ao');
        $this->post(route('avaliacao.lancamento.guardar', [$turma, $mat->id]), ['periodo' => 1, 'notas' => [$m->id => ['AC' => [1 => '20']]]])
            ->assertSessionHas('erro');
        $this->assertFalse(Nota::where('matricula_id', $m->id)->where('valor', 20)->exists());
        $this->post(route('avaliacao.periodos.reabrir'), ['ano_lectivo_id' => $ano->id, 'periodo' => 1, 'motivo' => 'Correcção de erro de lançamento'])->assertForbidden();

        $this->entrarComo('director@sige.demo.ao');
        $this->post(route('avaliacao.periodos.reabrir'), ['ano_lectivo_id' => $ano->id, 'periodo' => 1])->assertSessionHasErrors('motivo');
        $this->post(route('avaliacao.periodos.reabrir'), ['ano_lectivo_id' => $ano->id, 'periodo' => 1, 'motivo' => 'Correcção de erro de lançamento'])->assertSessionHas('sucesso');
        $this->assertFalse(PeriodoLancamento::estaFechado($turma->escola_id, $ano->id, 1));
        $this->assertDatabaseHas('logs_actividade', ['accao' => 'reabrir']);
    }

    public function test_ano_anterior_esta_fechado(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $anterior = AnoLectivo::where('designacao', '2025/2026')->first();
        $turma = Turma::where('ano_lectivo_id', $anterior->id)->first();
        $d = $turma->planoCurricular()->disciplinas->first();
        $m = $turma->matriculas()->first();
        $this->post(route('avaliacao.lancamento.guardar', [$turma, $d->id]), ['periodo' => 2, 'notas' => [$m->id => ['AC' => [1 => '1']]]])
            ->assertSessionHas('erro');
    }

    public function test_provincia_consulta_pautas_sem_lancar(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $turma = $this->turma7();
        $this->post(route('contexto.definir'), ['escola_id' => $turma->escola_id]);

        $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 1]))->assertOk();
        $this->post(route('avaliacao.lancamento.guardar', [$turma, $this->disciplina('MAT')->id]), ['periodo' => 1, 'notas' => []])->assertForbidden();
        $this->post(route('avaliacao.periodos.fechar'), ['ano_lectivo_id' => $this->ano()->id, 'periodo' => 1])->assertForbidden();
    }

    /* ---------------------------------------------------------- Pautas */

    public function test_pautas_html_pdf_excel_e_boletim(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $anterior = AnoLectivo::where('designacao', '2025/2026')->first();
        $turma = Turma::where('ano_lectivo_id', $anterior->id)->whereHas('classe', fn ($q) => $q->where('numero', 9))->firstOrFail();
        $d = $turma->planoCurricular()->disciplinas->first();
        $final = ResultadoFinal::whereHas('matricula', fn ($q) => $q->where('turma_id', $turma->id))->first();

        $this->get(route('avaliacao.pautas.index'))->assertOk();
        $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 0]))->assertOk()->assertSee('Pauta Final')->assertSee($final->rotulo);
        $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 2]))->assertOk();
        $this->get(route('avaliacao.pautas.disciplina', [$turma, $d->id]))->assertOk()->assertSee('MFED');
        $this->get(route('avaliacao.pautas.classe', [$turma->classe_id, 'ano_lectivo_id' => $anterior->id]))->assertOk();

        $pdf = $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 0, 'formato' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(route('avaliacao.pautas.turma', [$turma, 'periodo' => 0, 'formato' => 'xlsx']))->assertOk()
            ->assertHeader('content-disposition');

        $this->get(route('alunos.show', $final->matricula->aluno_id))->assertOk()->assertSee($final->rotulo);
    }

    /* ---------------------------------------------- Configuração/versões */

    private function dadosConfig(ConfiguracaoAvaliacao $c): array
    {
        $s = $c->snapshot();

        return $s['campos'] + [
            'classes' => $s['classes'], 'componentes' => $s['componentes'], 'formulas' => $s['formulas'],
            'regras' => array_map(fn ($r) => $r + ['activo' => $r['activo'] ? '1' : '0'], $s['regras']),
        ];
    }

    public function test_alteracao_da_configuracao_e_versionada_sem_efeito_retroactivo(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $config = ConfiguracaoAvaliacao::where('nome', 'like', '%9.ª classe%')->where('ano_lectivo_id', AnoLectivo::where('designacao', '2025/2026')->value('id'))->first();
        $versao = $config->versao;
        $antes = ResultadoAvaliacao::semFiltroEscola()->where('configuracao_avaliacao_id', $config->id)->where('periodo', 0)->whereNotNull('classificacao')->first();

        $dados = $this->dadosConfig($config);
        foreach ($dados['formulas'] as &$f) {
            if ($f['codigo'] === 'MFED') {
                $f['expressao'] = '0,5 * MT3 + 0,5 * EN';
            }
        }
        unset($f);

        $this->put(route('avaliacao.configuracoes.update', $config), $dados)->assertSessionHasErrors('motivo');
        $this->put(route('avaliacao.configuracoes.update', $config), $dados + ['motivo' => 'Teste de alteração de pesos'])->assertSessionHasNoErrors();

        $config->refresh();
        $this->assertSame($versao + 1, $config->versao);
        $this->assertTrue(HistoricoConfiguracao::where('entidade', 'configuracao')->where('entidade_id', $config->id)->where('versao', $config->versao)
            ->where('motivo', 'Teste de alteração de pesos')->exists());
        // Sem efeito retroactivo
        $this->assertEquals($antes->classificacao, $antes->fresh()->classificacao);
        $this->assertSame($versao, $antes->fresh()->configuracao_versao);

        // Recálculo controlado aplica a nova versão
        $turma = Turma::find($antes->turma_id);
        $this->post(route('contexto.definir'), ['escola_id' => $turma->escola_id]);
        $this->post(route('avaliacao.periodos.recalcular', $turma), ['motivo' => 'Aplicar nova versão da configuração'])->assertSessionHas('sucesso');
        $depois = $antes->fresh();
        $this->assertSame($config->versao, $depois->configuracao_versao);
        $this->assertEquals(round(0.5 * $depois->valores['MT3'] + 0.5 * $depois->valores['EN']), $depois->classificacao);
    }

    public function test_formulas_invalidas_sao_rejeitadas(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $config = ConfiguracaoAvaliacao::where('nome', 'like', 'I Ciclo — 7.ª%')->where('ano_lectivo_id', $this->ano()->id)->first();
        $dados = $this->dadosConfig($config) + ['motivo' => 'Teste de validação'];

        $dados['formulas'][1]['expressao'] = '(MACT + XPTO) / 2';
        $this->put(route('avaliacao.configuracoes.update', $config), $dados)->assertSessionHasErrors('formulas.1.expressao');

        $dados['formulas'][1]['expressao'] = 'system("dir")';
        $this->put(route('avaliacao.configuracoes.update', $config), $dados)->assertSessionHasErrors('formulas.1.expressao');

        $dados['formulas'][1]['expressao'] = '(MACT + NPT) / 2';
        $dados['variavel_final'] = 'MT';
        $this->put(route('avaliacao.configuracoes.update', $config), $dados)->assertSessionHasErrors('variavel_final');
        $this->assertSame(1, $config->fresh()->versao);
    }

    public function test_pre_visualizacao_usa_o_motor_real(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $config = ConfiguracaoAvaliacao::where('nome', 'like', '%12.ª classe%')->where('ano_lectivo_id', $this->ano()->id)->first();
        $dados = $this->dadosConfig($config) + ['teste' => [
            1 => ['AC' => '12; 14', 'NPT' => '13'], 2 => ['AC' => '10', 'NPT' => '12'], 3 => ['AC' => '15; 15'], 4 => ['EN' => '9'],
        ]];

        $r = $this->postJson(route('avaliacao.configuracoes.previsualizar'), $dados)->assertOk()->json();
        // MT1 = 13 ; MT2 = 11 ; MT3 = (13 + 11 + 15)/3 = 13 ; MFED = 0,5×13 + 0,5×9 = 11
        $this->assertEquals(13, $r['resultado']['trimestres'][1]['classificacao']);
        $this->assertEquals(11, $r['resultado']['trimestres'][2]['classificacao']);
        $this->assertEquals(13, $r['resultado']['trimestres'][3]['classificacao']);
        $this->assertEquals(11, $r['resultado']['final']['classificacao']);
        $this->assertSame('aprovado', $r['simulacao']['situacao']);

        $dados['formulas'][0]['expressao'] = 'MEDIA(AC';
        $this->postJson(route('avaliacao.configuracoes.previsualizar'), $dados)->assertStatus(422)->assertJsonStructure(['erros']);
    }

    public function test_activacao_com_conflito_e_duplicacao(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $activa = ConfiguracaoAvaliacao::where('nome', 'like', 'I Ciclo — 7.ª%')->where('ano_lectivo_id', $this->ano()->id)->first();

        // Duplicar para outro ano fica em rascunho
        $this->post(route('avaliacao.configuracoes.duplicar', $activa), ['ano_lectivo_id' => $this->ano()->id])->assertRedirect();
        $copia = ConfiguracaoAvaliacao::where('estado', 'rascunho')->latest('id')->first();
        $this->assertSame($activa->formulas->pluck('expressao')->all(), $copia->formulas->pluck('expressao')->all());
        $this->assertTrue(HistoricoConfiguracao::where('entidade_id', $copia->id)->where('accao', 'duplicacao')->exists());

        // Activar com outra activa para as mesmas classes → conflito
        $this->patch(route('avaliacao.configuracoes.estado', $copia), ['estado' => 'activa', 'motivo' => 'Teste de conflito'])->assertSessionHas('erro');
        $this->assertSame('rascunho', $copia->fresh()->estado);
    }

    public function test_escala_versionada_e_intervalos_validados(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $escala = EscalaClassificacao::where('nome', 'like', 'Ensino Secundário%')->first();
        $intervalos = $escala->intervalos->map->only('minimo', 'maximo', 'rotulo', 'sigla', 'valor_referencia')->values()->all();
        $base = ['nome' => $escala->nome, 'nota_minima' => 0, 'nota_maxima' => 20, 'motivo' => 'Teste'];

        $sobrepostos = $intervalos;
        $sobrepostos[1]['minimo'] = 15; // "Bom" a começar dentro de "Excelente"?? (17–20) não; força sobreposição com Suficiente
        $sobrepostos[1]['maximo'] = 18;
        $this->put(route('avaliacao.escalas.update', $escala), $base + ['intervalos' => $sobrepostos])->assertSessionHasErrors('intervalos');

        $intervalos[0]['rotulo'] = 'Muito Bom';
        $this->put(route('avaliacao.escalas.update', $escala), $base + ['intervalos' => $intervalos])->assertSessionHasNoErrors();
        $this->assertSame(2, $escala->fresh()->versao);
        $this->assertTrue($escala->fresh()->intervalos->contains('rotulo', 'Muito Bom'));
    }

    public function test_director_nao_configura_avaliacoes(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $this->get(route('avaliacao.configuracoes.index'))->assertForbidden();
        $this->get(route('avaliacao.escalas.index'))->assertForbidden();
    }
}
