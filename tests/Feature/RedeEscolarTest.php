<?php

namespace Tests\Feature;

use App\Models\Escola;
use App\Models\Municipio;
use App\Models\NivelEnsino;
use App\Models\Perfil;
use App\Models\TipologiaEscola;
use App\Models\User;
use Tests\TestCase;

class RedeEscolarTest extends TestCase
{
    private function dadosEscola(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Complexo Escolar de Teste',
            'codigo' => 'bgo-dan-9999',
            'municipio_id' => Municipio::where('codigo', 'BGO-DAN')->value('id'),
            'tipologia_id' => TipologiaEscola::where('sigla', 'CE')->value('id'),
            'natureza' => 'publico_privada',
            'niveis' => NivelEnsino::whereIn('codigo', ['PRIM', 'ESG1', 'ESG2'])->pluck('id')->all(),
            'latitude' => '-8,5700',
            'longitude' => '13,6600',
            'coordenadas_demonstrativas' => '1',
            'estado_funcionamento' => 'em_funcionamento',
            'ano_inicio_actividade' => 2020,
        ], $extra);
    }

    public function test_cadastro_de_escola_com_varios_niveis(): void
    {
        $this->entrarComo('admin@sige.demo.ao');

        $resposta = $this->post(route('escolas.store'), $this->dadosEscola());
        $escola = Escola::where('codigo', 'BGO-DAN-9999')->firstOrFail();
        $resposta->assertRedirect(route('escolas.show', $escola));

        $this->assertSame(3, $escola->niveisEnsino()->count());
        $this->assertEqualsWithDelta(-8.57, $escola->latitude, 0.0001); // vírgula decimal aceite
        $this->assertTrue($escola->coordenadas_demonstrativas);
        $this->assertDatabaseHas('logs_actividade', ['accao' => 'criar', 'modelo_tipo' => 'Escola', 'modelo_id' => $escola->id]);

        $this->get(route('escolas.show', $escola))->assertOk()
            ->assertSee('Ensino Primário')->assertSee('II Ciclo do Ensino Secundário Geral')->assertSee('Público-Privada');
    }

    public function test_escola_exige_pelo_menos_um_nivel_e_codigo_unico(): void
    {
        $this->entrarComo('admin@sige.demo.ao');

        $this->post(route('escolas.store'), $this->dadosEscola(['niveis' => [], 'codigo' => 'BGO-DAN-0101']))
            ->assertSessionHasErrors(['niveis', 'codigo']);
    }

    public function test_tipologia_inactiva_nao_pode_ser_usada_em_novas_escolas(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $tip = TipologiaEscola::where('sigla', 'CE')->first();
        $this->patch(route('tipologias.estado', $tip->id))->assertRedirect();
        $this->assertFalse($tip->fresh()->activo);

        $this->post(route('escolas.store'), $this->dadosEscola())->assertSessionHasErrors('tipologia_id');
    }

    public function test_actualizar_niveis_da_escola(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $escola = $this->escola('BGO-DAN-0310');
        $dados = $this->dadosEscola([
            'nome' => $escola->nome, 'codigo' => $escola->codigo,
            'niveis' => NivelEnsino::whereIn('codigo', ['ESG1', 'ESG2'])->pluck('id')->all(),
        ]);

        $this->put(route('escolas.update', $escola), $dados)->assertRedirect(route('escolas.show', $escola));
        $this->assertEqualsCanonicalizing(['ESG1', 'ESG2'], $escola->niveisEnsino()->pluck('codigo')->all());
    }

    public function test_filtros_da_lista_de_escolas(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $nivelAdultos = NivelEnsino::where('codigo', 'ADUL')->value('id');

        $this->get(route('escolas.index', ['nivel_id' => $nivelAdultos]))->assertOk()
            ->assertSee('Muxaluando')->assertSee('Bula Atumba')->assertDontSee('Liceu n.º 310');

        $this->get(route('escolas.index', ['natureza' => 'privada']))->assertOk()
            ->assertSee('Colégio Privado Horizonte')->assertSee('1 instituição');
    }

    public function test_crud_de_catalogos(): void
    {
        $this->entrarComo('admin@sige.demo.ao');

        $this->post(route('cadastros.niveis.store'), ['nome' => 'Ensino Recorrente', 'codigo' => 'rec', 'ordem' => 9])->assertSessionHasNoErrors();
        $nivel = NivelEnsino::where('codigo', 'REC')->firstOrFail();

        $this->put(route('cadastros.niveis.update', $nivel->id), ['nome' => 'Ensino Recorrente Nocturno', 'codigo' => 'REC'])->assertSessionHasNoErrors();
        $this->assertSame('Ensino Recorrente Nocturno', $nivel->fresh()->nome);

        $this->post(route('cadastros.niveis.store'), ['nome' => 'Ensino Primário', 'codigo' => 'X'])->assertSessionHasErrors('nome');
        $this->post(route('tipologias.store'), ['nome' => 'Escola Comunitária'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tipologias_escola', ['nome' => 'Escola Comunitária', 'activo' => true]);
    }

    public function test_municipios_crud_e_desactivacao(): void
    {
        $this->entrarComo('admin@sige.demo.ao');

        $this->post(route('municipios.store'), ['provincia_id' => \App\Models\Provincia::value('id'), 'nome' => 'Barra do Dande', 'codigo' => 'bgo-bdd', 'sede' => 'Barra do Dande', 'activo' => '1'])
            ->assertRedirect();
        $m = Municipio::where('codigo', 'BGO-BDD')->firstOrFail();
        $this->assertTrue($m->activo);

        $this->patch(route('municipios.estado', $m))->assertRedirect();
        $this->assertFalse($m->fresh()->activo);

        $this->post(route('escolas.store'), $this->dadosEscola(['municipio_id' => $m->id]))->assertSessionHasErrors('municipio_id');
    }

    public function test_criacao_de_utilizador_valida_ambito(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $municipal = Perfil::where('codigo', Perfil::DIRECCAO_MUNICIPAL)->value('id');
        $professor = Perfil::where('codigo', Perfil::PROFESSOR)->value('id');
        $base = ['nome' => 'Novo Técnico', 'email' => 'novo@sige.demo.ao', 'password' => 'Senha2026x', 'password_confirmation' => 'Senha2026x', 'activo' => '1'];

        $this->post(route('utilizadores.store'), $base + ['perfil_id' => $municipal])->assertSessionHasErrors('municipio_id');
        $this->post(route('utilizadores.store'), $base + ['perfil_id' => $professor])->assertSessionHasErrors('escolas');

        $escolas = [$this->escola('BGO-AMB-0120')->id, $this->escola('BGO-AMB-0501')->id];
        $this->post(route('utilizadores.store'), $base + ['perfil_id' => $professor, 'escolas' => $escolas, 'municipio_id' => 1])
            ->assertRedirect(route('utilizadores.index'));

        $u = User::where('email', 'novo@sige.demo.ao')->firstOrFail();
        $this->assertNull($u->municipio_id); // município ignorado para perfis de escola
        $this->assertEqualsCanonicalizing($escolas, $u->escolas()->pluck('escolas.id')->all());
        $this->assertNotSame('Senha2026x', $u->password); // senha guardada com hash
    }

    public function test_admin_nao_se_pode_desactivar(): void
    {
        $admin = $this->entrarComo('admin@sige.demo.ao');
        $this->patch(route('utilizadores.estado', $admin))->assertSessionHas('erro');
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_inicio_mostra_indicadores_reais(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $this->get(route('inicio'))->assertOk()
            ->assertSee('Escolas por tipologia institucional')
            ->assertViewHas('totalEscolas', 10)
            ->assertViewHas('totalMunicipios', 6);

        $this->entrarComo('municipal.dande@sige.demo.ao');
        $this->get(route('inicio'))->assertViewHas('totalEscolas', 4)->assertViewHas('totalMunicipios', 1);
    }
}
