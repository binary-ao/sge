<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\AtribuicaoProfessor;
use App\Models\Declaracao;
use App\Models\Falta;
use App\Models\JustificativoFalta;
use App\Models\Matricula;
use App\Models\Pagamento;
use App\Models\PeriodoLancamento;
use App\Models\Recibo;
use App\Models\ServicoPagavel;
use App\Models\Turma;
use App\Services\Administrativo\ServicoFrequencia;
use App\Services\Avaliacao\MotorRegras;
use App\Support\ContextoEscola;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AdministrativoTest extends TestCase
{
    private function ano(): AnoLectivo
    {
        return AnoLectivo::where('actual', true)->first();
    }

    private function turma7(): Turma
    {
        return Turma::where('ano_lectivo_id', $this->ano()->id)->whereHas('classe', fn ($q) => $q->where('numero', 7))->firstOrFail();
    }

    /* ----------------------------------------------------------- Faltas */

    public function test_chamada_regista_e_remove_faltas(): void
    {
        $this->entrarComo('professor@sige.demo.ao');
        $turma = $this->turma7();
        $prof = app(ContextoEscola::class)->professorActual();
        $disc = AtribuicaoProfessor::where('turma_id', $turma->id)->where('professor_id', $prof->id)->value('disciplina_id');
        [$a, $b] = $turma->matriculasActivas()->take(2)->pluck('id')->all();
        $data = now()->previousWeekday()->toDateString();
        Falta::where('turma_id', $turma->id)->whereDate('data', $data)->where('disciplina_id', $disc)->delete();

        $this->get(route('faltas.chamada', ['turma_id' => $turma->id, 'disciplina_id' => $disc, 'data' => $data]))->assertOk()->assertSee('Guardar chamada');
        $this->post(route('faltas.chamada.guardar'), ['turma_id' => $turma->id, 'disciplina_id' => $disc, 'data' => $data,
            'faltas' => [$a => ['falta' => 1, 'tempos' => 2, 'motivo' => 'Doença'], $b => ['falta' => 1]]])->assertSessionHas('sucesso');
        $this->assertSame(2, Falta::where('turma_id', $turma->id)->where('disciplina_id', $disc)->whereDate('data', $data)->count());
        $this->assertSame($prof->id, Falta::where('matricula_id', $a)->whereDate('data', $data)->value('professor_id'));

        // Desmarcar remove a falta
        $this->post(route('faltas.chamada.guardar'), ['turma_id' => $turma->id, 'disciplina_id' => $disc, 'data' => $data,
            'faltas' => [$a => ['falta' => 1, 'tempos' => 2]]])->assertSessionHas('sucesso');
        $this->assertFalse(Falta::where('matricula_id', $b)->where('disciplina_id', $disc)->whereDate('data', $data)->exists());

        // Datas futuras e disciplinas não atribuídas são recusadas
        $this->post(route('faltas.chamada.guardar'), ['turma_id' => $turma->id, 'disciplina_id' => $disc, 'data' => now()->addDays(3)->toDateString()])
            ->assertSessionHasErrors('data');
        $lp = $turma->planoCurricular()->disciplinas->firstWhere('sigla', 'LP')->id;
        $this->post(route('faltas.chamada.guardar'), ['turma_id' => $turma->id, 'disciplina_id' => $lp, 'data' => $data])->assertNotFound();
    }

    public function test_faltas_bloqueadas_em_periodo_fechado(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $turma = $this->turma7();
        $t1 = $this->ano()->trimestres->firstWhere('numero', 1);
        PeriodoLancamento::create(['ano_lectivo_id' => $this->ano()->id, 'periodo' => 1, 'estado' => 'fechado']);
        $data = $t1->data_inicio->copy()->addWeek()->startOfWeek()->toDateString();
        $disc = $turma->planoCurricular()->disciplinas->first()->id;
        $m = $turma->matriculasActivas()->first();

        $this->post(route('faltas.chamada.guardar'), ['turma_id' => $turma->id, 'disciplina_id' => $disc, 'data' => $data,
            'faltas' => [$m->id => ['falta' => 1]]])->assertSessionHas('erro');
    }

    public function test_justificativo_e_frequencia(): void
    {
        Storage::fake('local');
        $this->entrarComo('director@sige.demo.ao');
        $falta = Falta::where('justificacao', 'nao_justificada')->firstOrFail();
        $m = $falta->matricula;
        $antes = app(ServicoFrequencia::class)->resumo($m);
        $this->assertNotNull($antes['frequencia']);

        $this->post(route('faltas.justificar', $falta), ['motivo' => 'Doença com atestado', 'documento' => UploadedFile::fake()->create('atestado.pdf', 50, 'application/pdf')])
            ->assertSessionHas('sucesso');
        $this->assertSame('pendente', $falta->fresh()->justificacao);
        $just = JustificativoFalta::where('falta_id', $falta->id)->firstOrFail();
        $this->get(route('faltas.documento', $just))->assertOk();

        $this->patch(route('faltas.analisar', $just), ['estado' => 'rejeitado'])->assertSessionHasErrors('observacao');
        $this->patch(route('faltas.analisar', $just), ['estado' => 'aprovado'])->assertSessionHas('sucesso');
        $this->assertSame('aprovado', $falta->fresh()->justificacao);

        // A falta justificada deixa de contar para a frequência.
        $depois = app(ServicoFrequencia::class)->resumo($m->fresh());
        $this->assertSame($antes['contabilizadas'] - $falta->tempos, $depois['contabilizadas']);
        $this->assertGreaterThanOrEqual($antes['frequencia'], $depois['frequencia']);
    }

    public function test_professor_nao_analisa_justificativos(): void
    {
        $this->entrarComo('professor@sige.demo.ao');
        $j = JustificativoFalta::where('estado', 'pendente')->first();
        $this->patch(route('faltas.analisar', $j), ['estado' => 'aprovado'])->assertForbidden();
    }

    public function test_frequencia_entra_na_regra_de_retencao(): void
    {
        $motor = app(MotorRegras::class);
        $regras = [['tipo' => 'frequencia_minima', 'parametros' => ['percentagem' => 66.67], 'activo' => true],
            ['tipo' => 'nota_minima_disciplina', 'parametros' => ['nota' => 10], 'activo' => true]];
        $disc = [['nome' => 'Matemática', 'nuclear' => true, 'especifica' => false, 'classificacao' => 14]];
        $this->assertSame('retido_faltas', $motor->avaliar($regras, 'transicao', $disc, 60.0, 10)['situacao']);
        $this->assertSame('transita', $motor->avaliar($regras, 'transicao', $disc, 80.0, 10)['situacao']);

        // Turma sem qualquer registo de faltas: frequência "não verificada" (não assume 100%).
        $anterior = AnoLectivo::where('designacao', '2025/2026')->first();
        $m = Matricula::semFiltroEscola()->where('ano_lectivo_id', $anterior->id)->whereNotNull('turma_id')->first();
        $this->assertNull(app(ServicoFrequencia::class)->percentagem($m));
    }

    /* ------------------------------------------------------- Pagamentos */

    public function test_pagamento_emite_recibo_sequencial_e_nao_paga_duas_vezes(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');
        $this->post(route('contexto.definir'), []); // secretaria: escola fixa
        $mensalidade = ServicoPagavel::create(['tipo' => 'mensalidade', 'nome' => 'Mensalidade de teste', 'valor' => 5000, 'activo' => true]);
        $matricula = Matricula::with('aluno')->where('ano_lectivo_id', $this->ano()->id)->where('estado', 'activa')->first();
        $ultimo = (int) Recibo::where('ano', now()->year)->max('sequencial');
        $declaracao = ServicoPagavel::where('tipo', 'declaracao')->first();

        $this->get(route('pagamentos.create', ['aluno' => $matricula->aluno_id]))->assertOk()->assertSee('Mensalidade de teste');
        $this->post(route('pagamentos.store'), ['aluno_id' => $matricula->aluno_id, 'mensalidade_id' => $mensalidade->id,
            'meses' => [$this->ano()->data_inicio->year.'-10', $this->ano()->data_inicio->year.'-11'], 'servicos' => [$declaracao->id],
            'forma_pagamento' => 'tpa', 'data' => now()->toDateString()])->assertSessionHasErrors('referencia');

        $this->post(route('pagamentos.store'), ['aluno_id' => $matricula->aluno_id, 'mensalidade_id' => $mensalidade->id,
            'meses' => [$this->ano()->data_inicio->year.'-10', $this->ano()->data_inicio->year.'-11'], 'servicos' => [$declaracao->id],
            'forma_pagamento' => 'tpa', 'referencia' => 'TPA123', 'data' => now()->toDateString()])->assertRedirect();

        $recibo = Recibo::latest('id')->first();
        $this->assertSame($ultimo + 1, $recibo->sequencial);
        $this->assertSame('RC '.now()->year.'/'.str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT), $recibo->numero);
        $this->assertEquals(10000 + $declaracao->valor, $recibo->total);
        $this->assertSame(3, $recibo->pagamentos()->count());

        // Mês já pago
        $this->post(route('pagamentos.store'), ['aluno_id' => $matricula->aluno_id, 'mensalidade_id' => $mensalidade->id,
            'meses' => [$this->ano()->data_inicio->year.'-10'], 'forma_pagamento' => 'numerario', 'data' => now()->toDateString()])
            ->assertSessionHasErrors('itens');

        $pdf = $this->get(route('pagamentos.recibo.pdf', $recibo));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_recibo_nao_se_elimina_apenas_anula(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $recibo = Recibo::where('estado', 'emitido')->firstOrFail();

        try {
            $recibo->delete();
            $this->fail('Um recibo não pode ser eliminado.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('recibos', ['id' => $recibo->id]);
        }
        try {
            $recibo->update(['total' => 1]);
            $this->fail('Um recibo emitido não pode ser alterado.');
        } catch (RuntimeException) {
        }

        $this->patch(route('pagamentos.anular', $recibo), ['motivo' => 'curto'])->assertSessionHasErrors('motivo');
        $this->patch(route('pagamentos.anular', $recibo), ['motivo' => 'Valor registado com erro'])->assertSessionHas('sucesso');
        $recibo->refresh();
        $this->assertSame('anulado', $recibo->estado);
        $this->assertNotNull($recibo->anulado_por);
        $this->assertSame(0, $recibo->pagamentos()->where('estado', 'valido')->count());
        $this->assertDatabaseHas('logs_actividade', ['accao' => 'anular', 'modelo_tipo' => 'Recibo', 'modelo_id' => $recibo->id]);

        $this->entrarComo('secretaria@sige.demo.ao');
        $outro = Recibo::where('estado', 'emitido')->first();
        $this->patch(route('pagamentos.anular', $outro), ['motivo' => 'Sem permissão para anular'])->assertForbidden();
    }

    public function test_mensalidades_em_atraso(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $this->post(route('contexto.definir'), []);
        $this->entrarComo('admin@sige.demo.ao');
        $privada = $this->escola('BGO-AMB-0501');
        $this->post(route('contexto.definir'), ['escola_id' => $privada->id]);

        $this->get(route('pagamentos.atraso'))->assertOk()
            ->assertViewHas('lista', fn ($l) => $l->isNotEmpty() && $l->every(fn ($x) => $x['meses']->isNotEmpty()));
        $this->get(route('pagamentos.index'))->assertOk();
        $this->get(route('servicos.index'))->assertOk()->assertSee('Propina mensal');
    }

    public function test_pagamentos_isolados_por_escola(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $alheio = Recibo::semFiltroEscola()->where('escola_id', $this->escola('BGO-AMB-0501')->id)->first();
        $this->get(route('pagamentos.recibo', $alheio))->assertNotFound();
        $alunoAlheio = Aluno::semFiltroEscola()->where('escola_id', $this->escola('BGO-AMB-0501')->id)->first();
        $this->post(route('pagamentos.store'), ['aluno_id' => $alunoAlheio->id, 'servicos' => [ServicoPagavel::first()->id],
            'forma_pagamento' => 'numerario', 'data' => now()->toDateString()])->assertNotFound();
    }

    /* ------------------------------------------------------ Declarações */

    public function test_declaracoes_emitidas_numeradas_e_imutaveis(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');
        $m = Matricula::where('ano_lectivo_id', $this->ano()->id)->where('estado', 'activa')->first();
        $antes = Declaracao::count();

        foreach (['matricula', 'frequencia', 'notas'] as $tipo) {
            $this->post(route('declaracoes.store'), ['aluno_id' => $m->aluno_id, 'matricula_id' => $m->id, 'tipo' => $tipo, 'finalidade' => 'para efeitos de teste'])
                ->assertRedirect(route('declaracoes.index'));
        }
        $this->assertSame($antes + 3, Declaracao::count());
        $d = Declaracao::where('tipo', 'notas')->latest('id')->first();
        $this->assertNotEmpty($d->conteudo['notas']);
        $this->assertSame(10, strlen($d->codigo_verificacao));

        $pdf = $this->get(route('declaracoes.pdf', $d));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->expectException(RuntimeException::class);
        $d->update(['finalidade' => 'alterada']);
    }

    public function test_declaracao_com_notas_exige_classificacoes(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $aluno = new Aluno(['nome' => 'Aluno Sem Notas', 'genero' => 'M', 'data_nascimento' => '2012-01-01', 'nacionalidade' => 'Angolana']);
        $aluno->numero_processo = '999999';
        $aluno->estado = 'activo';
        $aluno->save();
        $turma = $this->turma7();
        $m = Matricula::create(['aluno_id' => $aluno->id, 'ano_lectivo_id' => $this->ano()->id, 'classe_id' => $turma->classe_id, 'turma_id' => $turma->id,
            'turno_id' => $turma->turno_id, 'numero' => 'T-1', 'tipo' => 'nova', 'data_matricula' => now()->toDateString(), 'estado' => 'activa']);

        $this->post(route('declaracoes.store'), ['aluno_id' => $aluno->id, 'matricula_id' => $m->id, 'tipo' => 'notas'])->assertSessionHasErrors('tipo');
        $this->post(route('declaracoes.store'), ['aluno_id' => $aluno->id, 'matricula_id' => $m->id, 'tipo' => 'matricula'])->assertRedirect();
    }

    public function test_paginas_administrativas_e_ficha_do_aluno(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $aluno = Falta::first()->matricula->aluno;
        foreach ([route('faltas.index'), route('faltas.index', ['justificacao' => 'pendente']), route('faltas.chamada'), route('pagamentos.index'),
            route('pagamentos.atraso'), route('pagamentos.create'), route('servicos.index'), route('declaracoes.index'), route('declaracoes.create'),
            route('declaracoes.create', ['aluno' => $aluno->id]), route('paineis.escola')] as $url) {
            $this->assertSame(200, $this->get($url)->getStatusCode(), $url);
        }
        $this->get(route('alunos.show', $aluno))->assertOk()->assertSee('Assiduidade')->assertSee('Pagamentos')->assertSee('Declarações emitidas');
    }
}
