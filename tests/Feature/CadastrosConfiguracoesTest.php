<?php

namespace Tests\Feature;

use App\Models\AnoLectivo;
use App\Models\Ciclo;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Escola;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\TipologiaEscola;
use App\Models\Trimestre;
use App\Services\Indicadores\Filtros;
use App\Services\Indicadores\ServicoIndicadores;
use App\Support\ContextoEscola;
use Tests\TestCase;

/**
 * Módulo "Cadastros e Configurações": cadastro, edição, desactivação lógica,
 * integridade e controlo de acesso por perfil e jurisdição (sempre no servidor).
 */
class CadastrosConfiguracoesTest extends TestCase
{
    private function nivel(string $codigo): int
    {
        return NivelEnsino::where('codigo', $codigo)->value('id');
    }

    private function dadosEscola(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Escola Primária de Teste',
            'codigo' => 'BGO-TST-0001',
            'municipio_id' => Municipio::where('codigo', 'BGO-DAN')->value('id'),
            'tipologia_id' => TipologiaEscola::where('activo', true)->value('id'),
            'natureza' => 'publica',
            'niveis' => [$this->nivel('PRIM')],
            'latitude' => '-8.60', 'longitude' => '13.70',
            'estado_funcionamento' => 'em_funcionamento',
        ], $extra);
    }

    /* ======================================================= Dados existentes e páginas */

    public function test_dados_existentes_preservados_e_associados(): void
    {
        // Todas as disciplinas de referência ficaram associadas a pelo menos um nível de ensino.
        $this->assertSame(0, Disciplina::withoutGlobalScopes()->whereDoesntHave('niveisEnsino')->count());
        // A Matemática do I Ciclo veio dos planos curriculares existentes.
        $mat = Disciplina::withoutGlobalScopes()->whereNull('escola_id')->where('sigla', 'MAT')->first();
        $this->assertContains('ESG1', $mat->niveisEnsino->pluck('codigo')->all());
        // Todas as escolas do MVP estão activas.
        $this->assertSame(Escola::count(), Escola::where('activo', true)->count());
    }

    public function test_todas_as_paginas_do_modulo_abrem_com_os_registos_existentes(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $ano = AnoLectivo::where('actual', true)->first();

        $paginas = [
            route('municipios.index') => 'Dande',
            route('municipios.show', Municipio::where('codigo', 'BGO-DAN')->first()) => 'Alunos activos',
            route('escolas.index') => 'Liceu n.º 310',
            route('cadastros.disciplinas.index') => 'Matemática',
            route('cadastros.disciplinas.show', Disciplina::whereNull('escola_id')->where('sigla', 'MAT')->value('id')) => 'Classes em que é aplicada',
            route('cadastros.niveis.index') => 'Ensino Primário',
            route('cadastros.niveis.show', $this->nivel('ESG1')) => 'Escolas que ministram este nível',
            route('cadastros.classes.index') => '7.ª Classe',
            route('cadastros.classes.show', Classe::where('nome', '7.ª Classe')->first()) => 'Plano de referência',
            route('cadastros.anos.index') => '2026/2027',
            route('cadastros.anos.show', $ano) => 'Períodos de avaliação',
            route('cadastros.periodos.index') => 'I Trimestre',
            route('cadastros.periodos.show', $ano->trimestres->first()) => 'Lançamento de notas por escola',
        ];
        foreach ($paginas as $url => $texto) {
            $this->get($url)->assertOk()->assertSee($texto);
        }

        // Menu lateral: secção com os 7 submenus.
        $menu = $this->get(route('inicio'))->assertOk();
        foreach (['Cadastros e Configurações', 'Municípios', 'Escolas', 'Disciplinas', 'Níveis de Ensino', 'Classes e Ciclos', 'Anos Lectivos', 'Períodos de Avaliação'] as $item) {
            $menu->assertSee($item);
        }
    }

    public function test_pesquisa_filtros_e_paginacao(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');

        $this->get(route('cadastros.disciplinas.index', ['q' => 'Matem']))->assertOk()->assertSee('Matemática</a>', false)->assertDontSee('Língua Portuguesa</a>', false);
        $this->get(route('cadastros.disciplinas.index', ['nivel_id' => $this->nivel('PRE')]))->assertOk()->assertDontSee('Física</a>', false);
        $this->get(route('cadastros.classes.index', ['nivel_id' => $this->nivel('PRIM')]))->assertOk()->assertSee('6.ª Classe')->assertDontSee('10.ª Classe');
        $this->get(route('cadastros.anos.index', ['estado' => 'encerrado']))->assertOk()->assertSee('2025/2026')->assertDontSee('2026/2027</a>', false);
        $this->get(route('cadastros.periodos.index', ['ano_lectivo_id' => 0, 'numero' => 2]))->assertOk()->assertSee('II Trimestre</a>', false)->assertDontSee('III Trimestre</a>', false)->assertDontSee('>I Trimestre</a>', false);
        $this->get(route('cadastros.disciplinas.index', ['page' => 2]))->assertOk();
    }

    /* ======================================================= Direcção Provincial */

    public function test_provincial_gere_municipios_e_integridade_da_desactivacao(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $provincia = Municipio::first()->provincia_id;

        $this->post(route('municipios.store'), ['provincia_id' => $provincia, 'nome' => 'Barra do Dande', 'codigo' => 'bgo-bdd', 'activo' => '1'])
            ->assertSessionHasNoErrors();
        $novo = Municipio::where('codigo', 'BGO-BDD')->firstOrFail();

        // Duplicação bloqueada (nome na mesma província e código).
        $this->post(route('municipios.store'), ['provincia_id' => $provincia, 'nome' => 'Barra do Dande', 'codigo' => 'BGO-X'])->assertSessionHasErrors('nome');
        $this->post(route('municipios.store'), ['provincia_id' => $provincia, 'nome' => 'Outro', 'codigo' => 'BGO-BDD'])->assertSessionHasErrors('codigo');

        $this->put(route('municipios.update', $novo), ['provincia_id' => $provincia, 'nome' => 'Barra do Dande', 'codigo' => 'BGO-BDD', 'sede' => 'Barra do Dande', 'activo' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Barra do Dande', $novo->fresh()->sede);

        // Município com escolas activas não pode ser desactivado.
        $dande = Municipio::where('codigo', 'BGO-DAN')->first();
        $this->patch(route('municipios.estado', $dande))->assertSessionHas('erro');
        $this->assertTrue($dande->fresh()->activo);

        // Sem escolas: desactivação lógica (o registo mantém-se).
        $this->patch(route('municipios.estado', $novo))->assertSessionHas('sucesso');
        $this->assertFalse($novo->fresh()->activo);
        $this->assertDatabaseHas('municipios', ['id' => $novo->id]);
    }

    public function test_provincial_cadastra_edita_e_desactiva_escola(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');

        $this->post(route('escolas.store'), $this->dadosEscola())->assertSessionHasNoErrors();
        $escola = Escola::where('codigo', 'BGO-TST-0001')->firstOrFail();
        $this->assertTrue($escola->activo);

        // Código institucional único.
        $this->post(route('escolas.store'), $this->dadosEscola(['nome' => 'Outra']))->assertSessionHasErrors('codigo');

        $this->put(route('escolas.update', $escola), $this->dadosEscola(['telefone' => '923000000', 'latitude' => '-8.61']))->assertSessionHasNoErrors();
        $this->assertSame('923000000', $escola->fresh()->telefone);
        $this->assertEqualsWithDelta(-8.61, $escola->fresh()->latitude, 0.0001);

        $indicadores = app(ServicoIndicadores::class);
        $this->assertContains($escola->id, $indicadores->escolas(new Filtros()));

        $this->patch(route('escolas.estado', $escola))->assertSessionHas('sucesso');
        $this->assertFalse($escola->fresh()->activo);
        $this->assertDatabaseHas('escolas', ['id' => $escola->id]); // nada é eliminado

        // Escola desactivada: fora dos indicadores e não pode ser escolhida como escola activa.
        $this->assertNotContains($escola->id, app(ServicoIndicadores::class)->escolas(new Filtros()));
        $this->post(route('contexto.definir'), ['escola_id' => $escola->id])->assertSessionHas('erro');
        $this->assertNull(app(ContextoEscola::class)->escolaActivaId());
        $this->get(route('paineis.provincial'))->assertOk();
        $this->get(route('escolas.index', ['registo' => 'inactivo']))->assertOk()->assertSee('Escola Primária de Teste');

        $this->patch(route('escolas.estado', $escola))->assertSessionHas('sucesso');
        $this->assertTrue($escola->fresh()->activo);
    }

    public function test_provincial_gere_disciplinas_de_referencia_sem_duplicacao(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');

        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Educação Financeira', 'sigla' => 'efin', 'componente' => 'complementar', 'niveis' => [$this->nivel('ESG2'), $this->nivel('ETP')]])
            ->assertSessionHasNoErrors();
        $d = Disciplina::whereNull('escola_id')->where('sigla', 'EFIN')->firstOrFail();
        $this->assertEqualsCanonicalizing(['ESG2', 'ETP'], $d->niveisEnsino->pluck('codigo')->all());

        // Duplicações e validação.
        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Educação Financeira', 'sigla' => 'EFIN2', 'componente' => 'geral', 'niveis' => [$this->nivel('ESG2')]])
            ->assertSessionHasErrors('nome');
        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Outra', 'sigla' => 'EFIN', 'componente' => 'geral', 'niveis' => [$this->nivel('ESG2')]])
            ->assertSessionHasErrors('sigla');
        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Sem nível', 'sigla' => 'SN', 'componente' => 'geral'])
            ->assertSessionHasErrors('niveis');

        $this->put(route('cadastros.disciplinas.update', $d->id), ['nome' => 'Educação Financeira', 'sigla' => 'EFIN', 'componente' => 'complementar', 'niveis' => [$this->nivel('ETP')]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['ETP'], $d->fresh()->niveisEnsino->pluck('codigo')->all());

        $this->patch(route('cadastros.disciplinas.estado', $d->id))->assertSessionHas('sucesso');
        $this->assertFalse($d->fresh()->activo);
    }

    public function test_provincial_gere_niveis_classes_e_ciclos_com_integridade(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');

        // Níveis
        $this->post(route('cadastros.niveis.store'), ['nome' => 'Ensino Especial Profissional', 'codigo' => 'eep', 'ordem' => 20, 'exige_curso' => '1'])->assertSessionHasNoErrors();
        $nivel = NivelEnsino::where('codigo', 'EEP')->firstOrFail();
        $this->assertTrue($nivel->exige_curso);
        $this->post(route('cadastros.niveis.store'), ['nome' => 'Ensino Primário', 'codigo' => 'X2'])->assertSessionHasErrors('nome');

        // Ciclo e classe
        $this->post(route('cadastros.ciclos.store'), ['nivel_ensino_id' => $nivel->id, 'nome' => 'Ciclo Único', 'ordem' => 1])->assertSessionHasNoErrors();
        $ciclo = Ciclo::where('nivel_ensino_id', $nivel->id)->firstOrFail();
        $this->post(route('cadastros.classes.store'), ['nivel_ensino_id' => $nivel->id, 'ciclo_id' => $ciclo->id, 'nome' => '1.º Ano', 'numero' => 1, 'regime' => 'transicao'])
            ->assertSessionHasNoErrors();
        $classe = Classe::where('nivel_ensino_id', $nivel->id)->firstOrFail();
        $this->assertSame($ciclo->id, $classe->ciclo_id);

        // Classe duplicada no mesmo nível e ciclo de outro nível: rejeitados.
        $this->post(route('cadastros.classes.store'), ['nivel_ensino_id' => $nivel->id, 'nome' => '1.º Ano', 'regime' => 'transicao'])->assertSessionHasErrors('nome');
        $outroCiclo = Ciclo::where('nivel_ensino_id', '!=', $nivel->id)->value('id');
        $this->post(route('cadastros.classes.store'), ['nivel_ensino_id' => $nivel->id, 'ciclo_id' => $outroCiclo, 'nome' => '2.º Ano', 'regime' => 'transicao'])->assertSessionHasErrors('ciclo_id');

        // Integridade na desactivação: nível com classes activas e ciclo com classes activas.
        $this->patch(route('cadastros.niveis.estado', $nivel))->assertSessionHas('erro');
        $this->patch(route('cadastros.ciclos.estado', $ciclo))->assertSessionHas('erro');
        $this->patch(route('cadastros.classes.estado', $classe))->assertSessionHas('sucesso');
        $this->patch(route('cadastros.ciclos.estado', $ciclo))->assertSessionHas('sucesso');
        $this->patch(route('cadastros.niveis.estado', $nivel))->assertSessionHas('sucesso');
        $this->assertFalse($nivel->fresh()->activo);

        // Classe com turmas no ano actual não pode ser desactivada.
        $setima = Classe::where('nome', '7.ª Classe')->first();
        $this->patch(route('cadastros.classes.estado', $setima))->assertSessionHas('erro');
        $this->assertTrue($setima->fresh()->activo);
    }

    public function test_anos_lectivos_e_periodos_com_validacao(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');

        // Sobreposição com o ano em curso e segundo ano "em curso": rejeitados.
        $this->post(route('cadastros.anos.store'), ['designacao' => '2027', 'data_inicio' => '2027-06-01', 'data_fim' => '2028-05-31', 'estado' => 'planeado'])
            ->assertSessionHasErrors('data_inicio');
        $this->post(route('cadastros.anos.store'), ['designacao' => '2027/2028', 'data_inicio' => '2027-09-01', 'data_fim' => '2028-07-31', 'estado' => 'em_curso'])
            ->assertSessionHasErrors('estado');

        $this->post(route('cadastros.anos.store'), ['designacao' => '2027/2028', 'data_inicio' => '2027-09-01', 'data_fim' => '2028-07-31', 'estado' => 'planeado'])
            ->assertSessionHasNoErrors();
        $ano = AnoLectivo::where('designacao', '2027/2028')->firstOrFail();
        [$t1, $t2] = [$ano->trimestres[0], $ano->trimestres[1]];

        // Datas dos períodos: dentro do ano, sem sobreposição e pela ordem.
        $this->put(route('cadastros.periodos.update', $t1), ['data_inicio' => '2027-08-01', 'data_fim' => '2027-12-15'])->assertSessionHasErrors('data_inicio');
        $this->put(route('cadastros.periodos.update', $t1), ['data_inicio' => '2027-09-01', 'data_fim' => '2027-12-15'])->assertSessionHasNoErrors();
        $this->put(route('cadastros.periodos.update', $t2), ['data_inicio' => '2027-12-01', 'data_fim' => '2028-03-31'])->assertSessionHasErrors('data_inicio');
        $this->put(route('cadastros.periodos.update', $t2), ['data_inicio' => '2028-01-05', 'data_fim' => '2027-12-31'])->assertSessionHasErrors('data_fim');
        $this->put(route('cadastros.periodos.update', $t2), ['data_inicio' => '2028-01-05', 'data_fim' => '2028-04-05'])->assertSessionHasNoErrors();
        $this->assertSame('2028-04-05', $t2->fresh()->data_fim->toDateString());

        // O ano não pode encolher deixando um trimestre de fora.
        $this->put(route('cadastros.anos.update', $ano), ['designacao' => '2027/2028', 'data_inicio' => '2027-10-01', 'data_fim' => '2028-07-31', 'estado' => 'planeado'])
            ->assertSessionHasErrors('data_inicio');

        // Período em falta pode ser reposto; período existente não se duplica.
        $t1->delete();
        $this->post(route('cadastros.periodos.store'), ['ano_lectivo_id' => $ano->id, 'numero' => 2, 'data_inicio' => '2027-09-01', 'data_fim' => '2027-12-15'])->assertSessionHasErrors('numero');
        $this->post(route('cadastros.periodos.store'), ['ano_lectivo_id' => $ano->id, 'numero' => 1, 'data_inicio' => '2027-09-01', 'data_fim' => '2027-12-15'])->assertSessionHasNoErrors();
        $this->assertSame(3, $ano->trimestres()->count());

        // Um único ano activo.
        $this->assertSame(1, AnoLectivo::where('actual', true)->count());
    }

    /* ======================================================= Direcção Municipal */

    public function test_municipal_so_gere_escolas_do_seu_municipio(): void
    {
        $this->entrarComo('municipal.dande@sige.demo.ao');
        $ambriz = Municipio::where('codigo', 'BGO-AMB')->value('id');

        // Cadastro no próprio município: permitido; noutro município: rejeitado.
        $this->post(route('escolas.store'), $this->dadosEscola())->assertSessionHasNoErrors();
        $this->post(route('escolas.store'), $this->dadosEscola(['codigo' => 'BGO-TST-0002', 'municipio_id' => $ambriz]))->assertSessionHasErrors('municipio_id');
        $this->assertDatabaseMissing('escolas', ['codigo' => 'BGO-TST-0002']);

        // Escolas de outro município: inexistentes para este utilizador.
        $deAmbriz = $this->escola('BGO-AMB-0120');
        $this->get(route('escolas.edit', $deAmbriz))->assertNotFound();
        $this->put(route('escolas.update', $deAmbriz), $this->dadosEscola(['codigo' => $deAmbriz->codigo, 'municipio_id' => $ambriz]))->assertForbidden();
        $this->patch(route('escolas.estado', $deAmbriz))->assertNotFound();
        $this->assertTrue($deAmbriz->fresh()->activo);

        // Não pode transferir uma escola sua para outro município.
        $doDande = $this->escola('BGO-DAN-0310');
        $this->put(route('escolas.update', $doDande), $this->dadosEscola(['nome' => $doDande->nome, 'codigo' => $doDande->codigo, 'municipio_id' => $ambriz]))
            ->assertSessionHasErrors('municipio_id');

        // Municípios, níveis, classes, anos e disciplinas de referência: só consulta.
        $this->get(route('municipios.create'))->assertForbidden();
        $this->post(route('cadastros.niveis.store'), ['nome' => 'X', 'codigo' => 'X'])->assertForbidden();
        $this->post(route('cadastros.classes.store'), [])->assertForbidden();
        $this->post(route('cadastros.anos.store'), [])->assertForbidden();
        $this->post(route('cadastros.disciplinas.store'), [])->assertForbidden();
        $this->get(route('cadastros.disciplinas.index'))->assertOk();
    }

    /* ======================================================= Perfis de escola */

    public function test_director_gere_disciplinas_da_escola_e_consulta_o_resto(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $escolaId = $this->escola('BGO-DAN-0205')->id;

        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Língua Nacional Kikongo', 'sigla' => 'kik', 'componente' => 'complementar', 'niveis' => [$this->nivel('ESG1')]])
            ->assertSessionHasNoErrors();
        $this->assertSame($escolaId, Disciplina::where('sigla', 'KIK')->value('escola_id'));

        // Não duplica uma disciplina de referência do mesmo nível.
        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Matemática', 'sigla' => 'MAT9', 'componente' => 'geral', 'niveis' => [$this->nivel('ESG1')]])
            ->assertSessionHasErrors('nome');

        // Referências provinciais e estrutura: só consulta.
        $ref = Disciplina::whereNull('escola_id')->where('sigla', 'MAT')->first();
        $this->patch(route('cadastros.disciplinas.estado', $ref->id))->assertForbidden();
        $this->patch(route('cadastros.classes.estado', Classe::first()))->assertForbidden();
        $this->patch(route('cadastros.niveis.estado', $this->nivel('PRIM')))->assertForbidden();
        $this->patch(route('escolas.estado', $this->escola('BGO-DAN-0205')))->assertForbidden();
        $this->put(route('cadastros.periodos.update', Trimestre::first()), [])->assertForbidden();
        $this->get(route('escolas.create'))->assertForbidden();
        $this->get(route('cadastros.classes.index'))->assertOk();
    }

    public function test_professor_ve_apenas_disciplinas_e_classes_atribuidas(): void
    {
        $this->entrarComo('professor@sige.demo.ao');

        $this->get(route('cadastros.disciplinas.index'))->assertOk()->assertSee('Matemática</a>', false)->assertDontSee('Língua Portuguesa</a>', false);
        $lp = Disciplina::whereNull('escola_id')->where('sigla', 'LP')->value('id');
        $this->get(route('cadastros.disciplinas.show', $lp))->assertNotFound();

        $pagina = $this->get(route('cadastros.classes.index'))->assertOk();
        $visiveis = $pagina->viewData('classes')->pluck('id');
        $this->assertGreaterThan(0, $visiveis->count());
        $this->assertLessThan(Classe::count(), $pagina->viewData('classes')->total());
        $this->get(route('cadastros.classes.show', $visiveis->first()))->assertOk();
        $this->get(route('cadastros.classes.show', Classe::whereNotIn('id', $visiveis)->value('id')))->assertNotFound();

        $this->post(route('cadastros.disciplinas.store'), [])->assertForbidden();
        $this->get(route('municipios.index'))->assertForbidden();
    }

    public function test_secretaria_consulta_cadastros_sem_alterar(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');

        foreach (['cadastros.disciplinas.index', 'cadastros.niveis.index', 'cadastros.classes.index', 'cadastros.anos.index', 'cadastros.periodos.index'] as $rota) {
            $this->get(route($rota))->assertOk()->assertDontSee('data-bs-target="#modal', false);
        }
        $this->post(route('cadastros.anos.store'), [])->assertForbidden();
        $this->patch(route('cadastros.anos.actual', AnoLectivo::first()))->assertForbidden();
        $this->get(route('escolas.index'))->assertForbidden();
    }

    public function test_pessoal_de_escola_desactivada_perde_acesso_operacional(): void
    {
        $escola = $this->escola('BGO-DAN-0205');
        $escola->update(['activo' => false]);

        $this->entrarComo('secretaria@sige.demo.ao');
        $this->assertNull(app(ContextoEscola::class)->escolaActivaId());
        $this->get(route('alunos.index'))->assertRedirect();
    }
}
