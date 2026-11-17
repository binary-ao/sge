<?php

namespace Tests\Feature;

use App\Models\AnoLectivo;
use App\Models\Escola;
use App\Models\Municipio;
use App\Services\Relatorios\ServicoRelatorios;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaineisTest extends TestCase
{
    private function anoActual(): AnoLectivo
    {
        return AnoLectivo::where('actual', true)->first();
    }

    public function test_painel_provincial_calcula_indicadores_da_base_de_dados(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $anterior = AnoLectivo::where('designacao', '2025/2026')->first();

        $alunos = DB::table('matriculas')->where('ano_lectivo_id', $this->anoActual()->id)->whereIn('estado', ['activa', 'concluida'])->count();
        $finais = DB::table('resultados_finais')->where('ano_lectivo_id', $anterior->id)->where('situacao', '!=', 'incompleto');
        $aprovados = (clone $finais)->whereIn('situacao', ['aprovado', 'transita', 'transita_condicionalmente', 'progressao_automatica'])->count();
        $taxa = round($aprovados / (clone $finais)->count() * 100, 1);

        $this->get(route('paineis.provincial'))->assertOk()
            ->assertSee('Rede Escolar')->assertSee('Desempenho Académico')
            ->assertViewHas('totais', fn ($t) => $t['escolas'] === 10 && $t['alunos'] === $alunos && $t['municipios'] === 5)
            ->assertViewHas('desempenho', fn ($d) => $d['taxa_aprovacao'] === $taxa)
            ->assertViewHas('anoDesempenho', fn ($a) => $a->id === $anterior->id);
    }

    public function test_filtros_globais_actualizam_os_indicadores(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $ambriz = Municipio::where('codigo', 'BGO-AMB')->first();
        $esg1 = DB::table('niveis_ensino')->where('codigo', 'ESG1')->value('id');

        $this->get(route('paineis.provincial', ['municipio_id' => $ambriz->id]))->assertOk()
            ->assertViewHas('totais', fn ($t) => $t['escolas'] === 2 && $t['municipios'] === 1)
            ->assertViewHas('tabela', fn ($tab) => $tab->every(fn ($l) => $l['escola']->municipio_id === $ambriz->id));

        $alunosEsg1 = DB::table('matriculas as m')->join('classes as c', 'c.id', '=', 'm.classe_id')
            ->where('m.ano_lectivo_id', $this->anoActual()->id)->whereIn('m.estado', ['activa', 'concluida'])->where('c.nivel_ensino_id', $esg1)->count();
        $this->get(route('paineis.provincial', ['nivel_id' => $esg1]))->assertOk()
            ->assertViewHas('totais', fn ($t) => $t['alunos'] === $alunosEsg1);

        // Valores inválidos são ignorados, sem erro
        $this->get(route('paineis.provincial', ['municipio_id' => 'abc', 'natureza' => "x' OR 1=1"]))->assertOk()
            ->assertViewHas('totais', fn ($t) => $t['escolas'] === 10);
    }

    public function test_direccao_municipal_limitada_ao_seu_municipio(): void
    {
        $this->entrarComo('municipal.dande@sige.demo.ao');
        $dande = Municipio::where('codigo', 'BGO-DAN')->first();
        $ambriz = Municipio::where('codigo', 'BGO-AMB')->first();

        $this->get(route('paineis.municipal', ['municipio_id' => $ambriz->id]))->assertOk()
            ->assertViewHas('municipio', fn ($m) => $m->id === $dande->id)
            ->assertViewHas('totais', fn ($t) => $t['escolas'] === 4)
            ->assertDontSee('Colégio Privado Horizonte');

        // Escola de outro município no filtro: nenhum dado
        $outra = Escola::where('codigo', 'BGO-AMB-0501')->first();
        $this->get(route('paineis.municipal', ['escola_id' => $outra->id]))->assertViewHas('totais', fn ($t) => $t['escolas'] === 0 && $t['alunos'] === 0);

        $this->get(route('paineis.provincial'))->assertForbidden();
        $this->get(route('relatorios.show', 'comparacao-instituicoes'))->assertForbidden();
        $this->get(route('relatorios.show', 'alunos-escola'))->assertOk()->assertSee('Mabubas')->assertDontSee('Ambriz');
        $this->get(route('mapa'))->assertOk()->assertViewHas('pontos', fn ($p) => $p->count() === 4 && $p->every(fn ($x) => $x['municipio'] === 'Dande'));
    }

    public function test_painel_da_escola(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $this->get(route('paineis.escola'))->assertRedirect(route('contexto.escolher'));

        $this->entrarComo('director@sige.demo.ao');
        $escola = $this->escola('BGO-DAN-0205');
        $alunos = DB::table('matriculas')->where('escola_id', $escola->id)->where('ano_lectivo_id', $this->anoActual()->id)->whereIn('estado', ['activa', 'concluida'])->count();

        $this->get(route('paineis.escola'))->assertOk()->assertSee($escola->nome)
            ->assertViewHas('totais', fn ($t) => $t['alunos'] === $alunos && $t['escolas'] === 1)
            ->assertViewHas('porDisciplina', fn ($p) => $p->isNotEmpty())
            ->assertSee('Mensalidades em atraso')->assertSee('Justificativos pendentes');
        $this->get(route('paineis.provincial'))->assertForbidden();
    }

    public function test_mapa_da_rede(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $this->get(route('mapa'))->assertOk()->assertSee('Coordenadas demonstrativas', false)
            ->assertViewHas('pontos', fn ($p) => $p->count() === 10 && $p->every(fn ($x) => $x['demo'] === true));
        $tip = DB::table('tipologias_escola')->where('sigla', 'CE')->value('id');
        $this->get(route('mapa', ['tipologia_id' => $tip]))->assertViewHas('pontos', fn ($p) => $p->count() === 3);
    }

    public function test_relatorios_disponiveis_e_exportacao(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $this->get(route('relatorios.index'))->assertOk();

        foreach (ServicoRelatorios::catalogo() as $chave => $def) {
            if ($def[4] || ! $def[6]) {
                continue; // escolares e da Fase 6 testados abaixo
            }
            $this->get(route('relatorios.show', $chave))->assertOk()->assertSee($def[1]);
        }

        $pdf = $this->get(route('relatorios.show', ['aprovacao', 'formato' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get(route('relatorios.show', ['comparacao-instituicoes', 'formato' => 'xlsx']))->assertOk()->assertHeader('content-disposition');
        $this->get(route('relatorios.show', 'nao-existe'))->assertNotFound();

        // Relatórios escolares exigem escola activa: sem ela, o utilizador escolhe uma escola do seu âmbito.
        $this->get(route('relatorios.show', 'lista-alunos'))->assertRedirect(route('contexto.escolher'));
        $this->post(route('contexto.definir'), ['escola_id' => $this->escola('BGO-AMB-0120')->id]);
        $this->get(route('relatorios.show', 'lista-alunos'))->assertOk()->assertSee('Escola do I Ciclo n.º 120');
    }

    public function test_relatorios_escolares(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $this->get(route('relatorios.show', 'lista-alunos'))->assertOk()->assertSee('Processo');
        $this->get(route('relatorios.show', 'matriculas'))->assertOk();
        foreach (['faltas', 'pagamentos', 'pagamentos-atraso', 'recibos', 'declaracoes'] as $r) {
            $this->get(route('relatorios.show', $r))->assertOk();
        }
        $this->get(route('relatorios.show', ['faltas', 'agrupamento' => 'disciplina']))->assertOk()->assertSee('Disciplina');
        $this->get(route('relatorios.show', 'pautas'))->assertRedirect(route('avaliacao.pautas.index'));
        $this->get(route('relatorios.show', 'aprovacao'))->assertForbidden();

        $this->entrarComo('secretaria@sige.demo.ao');
        $this->get(route('relatorios.index'))->assertForbidden();
    }
}
