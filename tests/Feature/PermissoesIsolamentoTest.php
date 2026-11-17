<?php

namespace Tests\Feature;

use App\Models\Perfil;
use App\Models\Permissao;
use App\Support\ContextoEscola;
use Tests\TestCase;

/**
 * Verifica que as permissões e o isolamento de dados são aplicados no backend.
 */
class PermissoesIsolamentoTest extends TestCase
{
    public function test_superutilizador_acede_a_tudo(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        foreach (['inicio', 'escolas.index', 'municipios.index', 'tipologias.index', 'cadastros.niveis.index', 'utilizadores.index', 'perfis.index', 'logs.index', 'escolas.create'] as $rota) {
            $this->get(route($rota))->assertOk();
        }
    }

    public function test_direccao_provincial_gere_a_rede_mas_nao_o_sistema(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');

        $this->get(route('escolas.index'))->assertOk()->assertSee('10 instituições');
        $this->get(route('escolas.show', $this->escola('BGO-AMB-0120')))->assertOk();

        // Cadastros e configurações provinciais: permitido.
        $this->get(route('escolas.create'))->assertOk();
        $this->get(route('municipios.create'))->assertOk();
        $this->get(route('tipologias.index'))->assertOk();

        // Administração do sistema: proibido.
        $this->get(route('utilizadores.index'))->assertForbidden();
        $this->get(route('perfis.index'))->assertForbidden();
        $this->get(route('copias.index'))->assertForbidden();
        $this->post(route('utilizadores.store'), [])->assertForbidden();
    }

    public function test_escrita_operacional_bloqueada_para_provincia_mesmo_se_atribuida(): void
    {
        $perfil = Perfil::where('codigo', Perfil::DIRECCAO_PROVINCIAL)->first();
        $perfil->permissoes()->syncWithoutDetaching(Permissao::whereIn('codigo', ['notas.lancar', 'alunos.gerir'])->pluck('id'));

        $user = $this->utilizador('provincial@sige.demo.ao');
        $this->assertFalse($user->temPermissao('notas.lancar'));
        $this->assertFalse($user->temPermissao('alunos.gerir'));
        $this->assertTrue($user->temPermissao('escolas.ver'));
    }

    public function test_direccao_municipal_so_ve_o_seu_municipio(): void
    {
        $this->entrarComo('municipal.dande@sige.demo.ao');

        $this->get(route('escolas.index'))->assertOk()
            ->assertSee('Liceu n.º 310')
            ->assertDontSee('Ambriz</a>', false)
            ->assertSee('4 instituições');

        // Escola de outro município: inexistente para este utilizador.
        $this->get(route('escolas.show', $this->escola('BGO-AMB-0120')))->assertNotFound();
        $this->get(route('escolas.show', $this->escola('BGO-DAN-0310')))->assertOk();

        // Municípios: apenas o próprio.
        $this->get(route('municipios.index'))->assertOk()->assertSee('Dande')->assertDontSee('Nambuangongo');
        $ambriz = \App\Models\Municipio::where('codigo', 'BGO-AMB')->first();
        $this->get(route('municipios.show', $ambriz))->assertNotFound();
    }

    public function test_municipal_nao_pode_entrar_em_escola_de_outro_municipio(): void
    {
        $this->entrarComo('municipal.dande@sige.demo.ao');

        $this->post(route('contexto.definir'), ['escola_id' => $this->escola('BGO-AMB-0120')->id])->assertForbidden();
        $this->post(route('contexto.definir'), ['escola_id' => $this->escola('BGO-DAN-0205')->id])->assertRedirect();
        $this->assertSame($this->escola('BGO-DAN-0205')->id, session(ContextoEscola::SESSAO));
    }

    public function test_director_isolado_na_sua_escola(): void
    {
        $this->entrarComo('director@sige.demo.ao');

        $this->get(route('escolas.index'))->assertOk()->assertSee('1 instituição');
        $this->get(route('escolas.show', $this->escola('BGO-DAN-0205')))->assertOk();
        $this->get(route('escolas.show', $this->escola('BGO-DAN-0310')))->assertNotFound();

        $this->post(route('contexto.definir'), ['escola_id' => $this->escola('BGO-DAN-0310')->id])->assertForbidden();
        $this->assertSame($this->escola('BGO-DAN-0205')->id, app(ContextoEscola::class)->escolaActivaId());

        $this->get(route('utilizadores.index'))->assertForbidden();
        $this->get(route('escolas.edit', $this->escola('BGO-DAN-0205')))->assertForbidden();
    }

    public function test_professor_com_duas_escolas_alterna_apenas_entre_elas(): void
    {
        $this->entrarComo('professor@sige.demo.ao');

        $this->get(route('escolas.index'))->assertForbidden(); // professor não tem escolas.ver
        $this->post(route('contexto.definir'), ['escola_id' => $this->escola('BGO-DAN-0310')->id])->assertRedirect();
        $this->post(route('contexto.definir'), ['escola_id' => $this->escola('BGO-AMB-0120')->id])->assertForbidden();
        $this->post(route('contexto.definir'), ['escola_id' => ''])->assertSessionHas('erro');
    }

    public function test_menu_so_mostra_itens_permitidos(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');
        $this->get(route('inicio'))->assertOk()
            ->assertSee('Pagamentos')
            ->assertDontSee('Perfis e Permissões')
            ->assertDontSee('Configuração de Avaliações');
    }

    public function test_modulos_implementados_nao_aparecem_como_em_desenvolvimento(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');
        // Todos os módulos do menu estão implementados: a página "em desenvolvimento" deixa de os servir.
        $this->get(route('em-desenvolvimento', 'pagamentos'))->assertNotFound();
        $this->get(route('em-desenvolvimento', 'nao-existe'))->assertNotFound();
        $this->get(route('inicio'))->assertDontSee('Em breve');
        $this->assertEmpty(collect(config('menu'))->pluck('itens')->flatten(1)->filter(fn ($i) => ! empty($i['em_desenvolvimento'])));
    }

    public function test_alteracao_de_permissoes_de_perfil_e_registada(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $perfil = Perfil::where('codigo', Perfil::SECRETARIA)->first();
        $ids = $perfil->permissoes()->pluck('permissoes.id')->push(Permissao::where('codigo', 'faltas.ver')->value('id'))->all();

        $this->put(route('perfis.update', $perfil), ['permissoes' => $ids])->assertRedirect(route('perfis.index'));

        $this->assertTrue($this->utilizador('secretaria@sige.demo.ao')->temPermissao('faltas.ver'));
        $this->assertDatabaseHas('logs_actividade', ['accao' => 'permissoes', 'modelo_id' => $perfil->id]);

        $super = Perfil::where('codigo', Perfil::SUPERUTILIZADOR)->first();
        $this->put(route('perfis.update', $super), ['permissoes' => []])->assertSessionHas('erro');
    }
}
