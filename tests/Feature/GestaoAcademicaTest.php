<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\PlanoCurricular;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Turno;
use App\Support\ContextoEscola;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GestaoAcademicaTest extends TestCase
{
    private function ano(): AnoLectivo
    {
        return AnoLectivo::where('actual', true)->first();
    }

    private function classe(string $nivel, int|string $chave): Classe
    {
        return Classe::whereHas('nivelEnsino', fn ($q) => $q->where('codigo', $nivel))
            ->where(is_int($chave) ? 'numero' : 'nome', $chave)->firstOrFail();
    }

    private function dadosAluno(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Esperança Kiala Domingos',
            'genero' => 'F',
            'data_nascimento' => now()->subYears(13)->toDateString(),
            'naturalidade' => 'Caxito',
            'nacionalidade' => 'Angolana',
            'tipo_documento' => 'bi',
            'numero_identificacao' => '009988776LA041',
            'encarregado' => ['nome' => 'Domingos Kiala', 'parentesco' => 'Pai', 'telefone' => '+244 923 111 222'],
        ], $extra);
    }

    public function test_modulos_operacionais_exigem_escola_activa(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $this->get(route('alunos.index'))->assertRedirect(route('contexto.escolher'));
        $this->get(route('turmas.index'))->assertRedirect(route('contexto.escolher'));
    }

    public function test_paginas_da_escola_abrem_para_o_director(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $aluno = Aluno::first();
        $turma = Turma::first();
        $professor = Professor::first();
        $matricula = Matricula::where('estado', 'activa')->first();

        foreach ([
            route('alunos.index'), route('alunos.create'), route('alunos.show', $aluno), route('alunos.edit', $aluno),
            route('turmas.index'), route('turmas.create'), route('turmas.show', $turma), route('turmas.edit', $turma),
            route('professores.index'), route('professores.create'), route('professores.show', $professor), route('professores.edit', $professor),
            route('matriculas.index'), route('matriculas.create'), route('matriculas.create', ['aluno' => $aluno->id]), route('matriculas.edit', $matricula),
            route('cadastros.anos.index'), route('cadastros.classes.index'), route('academico.turnos.index'), route('academico.cursos.index'),
            route('cadastros.disciplinas.index'), route('academico.planos.index'), route('academico.planos.create'),
            route('escolas.show', $this->escola('BGO-DAN-0205')), route('inicio'),
        ] as $url) {
            $estado = $this->get($url)->getStatusCode();
            $this->assertSame(200, $estado, "GET {$url} devolveu {$estado}");
        }
    }

    public function test_dados_de_outra_escola_nao_sao_acessiveis(): void
    {
        $outra = $this->escola('BGO-DAN-0310');
        $aluno = Aluno::semFiltroEscola()->where('escola_id', $outra->id)->first();
        $turma = Turma::semFiltroEscola()->where('escola_id', $outra->id)->first();
        $professor = Professor::semFiltroEscola()->where('escola_id', $outra->id)->first();

        $this->entrarComo('director@sige.demo.ao');
        $this->get(route('alunos.show', $aluno))->assertNotFound();
        $this->put(route('alunos.update', $aluno), $this->dadosAluno())->assertNotFound();
        $this->get(route('turmas.show', $turma))->assertNotFound();
        $this->get(route('professores.edit', $professor))->assertNotFound();

        // As listagens só contêm dados da escola activa.
        $this->assertSame(0, Aluno::where('escola_id', $outra->id)->count());
        $this->get(route('alunos.index', ['q' => $aluno->nome]))->assertDontSee($aluno->numero_processo);
    }

    public function test_fluxo_completo_cadastro_matricula_e_turma(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');
        $escola = $this->escola('BGO-DAN-0205');
        $turma = Turma::where('classe_id', $this->classe('ESG1', 7)->id)->firstOrFail();
        $antes = $turma->matriculasActivas()->count();

        // 1. Cadastro do aluno (com tentativa de forjar escola_id)
        $this->post(route('alunos.store'), $this->dadosAluno(['escola_id' => $this->escola('BGO-AMB-0120')->id, 'accao' => 'matricular']))
            ->assertRedirect();
        $aluno = Aluno::where('numero_identificacao', '009988776LA041')->firstOrFail();
        $this->assertSame($escola->id, $aluno->escola_id);
        $this->assertSame('activo', $aluno->estado);
        $this->assertNotNull($aluno->encarregado);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $aluno->numero_processo);

        // 2. Matrícula com turma
        $this->post(route('matriculas.store'), [
            'aluno_id' => $aluno->id, 'ano_lectivo_id' => $this->ano()->id, 'classe_id' => $turma->classe_id,
            'turma_id' => $turma->id, 'tipo' => 'nova', 'data_matricula' => now()->toDateString(),
        ])->assertRedirect(route('alunos.show', $aluno))->assertSessionHasNoErrors();

        $matricula = $aluno->matriculas()->firstOrFail();
        $this->assertSame($turma->id, $matricula->turma_id);
        $this->assertSame($turma->turno_id, $matricula->turno_id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{5}$/', $matricula->numero);
        $this->assertSame($antes + 1, $turma->matriculasActivas()->count());

        // 3. Matrícula duplicada no mesmo ano lectivo
        $this->post(route('matriculas.store'), [
            'aluno_id' => $aluno->id, 'ano_lectivo_id' => $this->ano()->id, 'classe_id' => $turma->classe_id,
            'turno_id' => Turno::first()->id, 'tipo' => 'nova', 'data_matricula' => now()->toDateString(),
        ])->assertSessionHasErrors('ano_lectivo_id');
        $this->assertSame(1, $aluno->matriculas()->count());
    }

    public function test_menor_exige_encarregado(): void
    {
        $this->entrarComo('secretaria@sige.demo.ao');
        $dados = $this->dadosAluno();
        unset($dados['encarregado']);
        $this->post(route('alunos.store'), $dados)->assertSessionHasErrors(['encarregado.nome', 'encarregado.telefone']);
    }

    public function test_regras_de_matricula(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $aluno = new Aluno($this->dadosAluno(['numero_identificacao' => null]));
        $aluno->numero_processo = '999001';
        $aluno->estado = 'activo';
        $aluno->save();
        $base = ['aluno_id' => $aluno->id, 'ano_lectivo_id' => $this->ano()->id, 'turno_id' => Turno::first()->id,
            'tipo' => 'nova', 'data_matricula' => now()->toDateString()];

        // Nível não ministrado pela escola (Complexo n.º 205 não tem Pré-Escolar)
        $this->post(route('matriculas.store'), $base + ['classe_id' => $this->classe('PRE', 'Iniciação')->id])
            ->assertSessionHasErrors('classe_id');

        // II Ciclo exige curso
        $this->post(route('matriculas.store'), $base + ['classe_id' => $this->classe('ESG2', 11)->id])
            ->assertSessionHasErrors('curso_id');

        // Turma incompatível com a classe
        $turma10 = Turma::where('classe_id', $this->classe('ESG2', 10)->id)->firstOrFail();
        $this->post(route('matriculas.store'), $base + ['classe_id' => $this->classe('ESG1', 7)->id, 'turma_id' => $turma10->id])
            ->assertSessionHasErrors('turma_id');

        // Turma completa
        $turma7 = Turma::where('classe_id', $this->classe('ESG1', 7)->id)->firstOrFail();
        $turma7->update(['capacidade' => $turma7->matriculasActivas()->count()]);
        $this->post(route('matriculas.store'), $base + ['classe_id' => $turma7->classe_id, 'turma_id' => $turma7->id])
            ->assertSessionHasErrors('turma_id');

        // Sem turma: aceite, fica por distribuir
        $this->post(route('matriculas.store'), $base + ['classe_id' => $this->classe('ESG2', 11)->id, 'curso_id' => Curso::where('codigo', 'CEJ')->value('id')])
            ->assertSessionHasNoErrors();
        $this->assertNull($aluno->matriculas()->first()->turma_id);
    }

    public function test_distribuicao_de_alunos_e_retirada(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $classe = $this->classe('ESG1', 7);
        $turmaB = Turma::create(['ano_lectivo_id' => $this->ano()->id, 'classe_id' => $classe->id, 'turno_id' => Turno::first()->id,
            'nome' => 'B', 'capacidade' => 2, 'activo' => true]);
        $turmaA = Turma::where('classe_id', $classe->id)->where('nome', 'A')->firstOrFail();

        // Três alunos da turma A ficam sem turma
        $matriculas = $turmaA->matriculasActivas()->take(3)->get();
        foreach ($matriculas as $m) {
            $this->delete(route('turmas.retirar', [$turmaA, $m]))->assertRedirect();
        }
        $this->assertSame(3, Matricula::whereIn('id', $matriculas->pluck('id'))->whereNull('turma_id')->count());

        // Capacidade 2: a distribuição de 3 falha e nada é alterado (transacção)
        $this->post(route('turmas.distribuir', $turmaB), ['matriculas' => $matriculas->pluck('id')->all()])->assertSessionHasErrors('matriculas');
        $this->assertSame(0, $turmaB->matriculasActivas()->count());

        $this->post(route('turmas.distribuir', $turmaB), ['matriculas' => $matriculas->take(2)->pluck('id')->all()])->assertSessionHasNoErrors();
        $this->assertSame(2, $turmaB->matriculasActivas()->count());
    }

    public function test_designacao_de_turma_unica_e_nivel_da_escola(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $base = ['ano_lectivo_id' => $this->ano()->id, 'turno_id' => Turno::first()->id, 'capacidade' => 35];

        $this->post(route('turmas.store'), $base + ['classe_id' => $this->classe('ESG1', 7)->id, 'nome' => 'a'])->assertSessionHasErrors('nome');
        $this->post(route('turmas.store'), $base + ['classe_id' => $this->classe('PRE', 'Iniciação')->id, 'nome' => 'A'])->assertSessionHasErrors('classe_id');
        $this->post(route('turmas.store'), $base + ['classe_id' => $this->classe('ESG2', 11)->id, 'nome' => 'A'])->assertSessionHasErrors('curso_id');
        $this->post(route('turmas.store'), $base + ['classe_id' => $this->classe('ESG2', 11)->id, 'curso_id' => Curso::where('codigo', 'CH')->value('id'), 'nome' => 'A'])
            ->assertSessionHasNoErrors();
    }

    public function test_atribuicao_de_professores_segue_o_plano(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $turma = Turma::where('classe_id', $this->classe('ESG1', 9)->id)->firstOrFail();
        $plano = $turma->planoCurricular();
        $professor = Professor::where('activo', true)->first();
        $mat = Disciplina::where('sigla', 'MAT')->value('id');
        $foraDoPlano = Disciplina::where('sigla', 'TLP')->value('id');

        $atribuicoes = $plano->disciplinas->mapWithKeys(fn ($d) => [$d->id => $professor->id])->all();
        $atribuicoes[$foraDoPlano] = $professor->id;
        $atribuicoes[$mat] = null; // retirar Matemática

        $this->put(route('turmas.professores', $turma), ['professores' => $atribuicoes, 'director_turma_id' => $professor->id])->assertSessionHasNoErrors();

        $this->assertSame($plano->disciplinas->count() - 1, $turma->atribuicoes()->count());
        $this->assertFalse($turma->atribuicoes()->where('disciplina_id', $foraDoPlano)->exists());
        $this->assertSame($professor->id, $turma->fresh()->director_turma_id);
    }

    public function test_alterar_situacao_do_aluno_actualiza_matricula(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $matricula = Matricula::where('estado', 'activa')->first();

        $this->patch(route('alunos.estado', $matricula->aluno), ['estado' => 'desistente', 'data_estado' => now()->toDateString(), 'motivo' => 'Mudança de residência'])
            ->assertSessionHasNoErrors();

        $this->assertSame('desistente', $matricula->aluno->fresh()->estado);
        $this->assertSame('desistente', $matricula->fresh()->estado);
        $this->assertDatabaseHas('logs_actividade', ['modelo_tipo' => 'Aluno', 'modelo_id' => $matricula->aluno_id, 'accao' => 'actualizar']);
    }

    public function test_anular_matricula_liberta_lugar(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $matricula = Matricula::where('estado', 'activa')->whereNotNull('turma_id')->first();

        $this->patch(route('matriculas.anular', $matricula), ['motivo' => 'Registo em duplicado'])->assertSessionHasNoErrors();
        $this->assertSame('anulada', $matricula->fresh()->estado);
        $this->assertNull($matricula->fresh()->turma_id);
        $this->assertDatabaseHas('matriculas', ['id' => $matricula->id]); // não é eliminada
    }

    public function test_professor_ve_apenas_as_suas_turmas_e_alunos(): void
    {
        $this->entrarComo('professor@sige.demo.ao');
        $prof = app(ContextoEscola::class)->professorActual();
        $this->assertNotNull($prof);
        $minhas = Turma::doProfessor($prof)->pluck('id');
        $outra = Turma::whereNotIn('id', $minhas)->first();

        $this->get(route('turmas.index'))->assertOk()->assertViewHas('turmas', fn ($t) => $t->pluck('id')->sort()->values()->all() === $minhas->sort()->values()->all());
        if ($outra) {
            $this->get(route('turmas.show', $outra))->assertNotFound();
            $alunoFora = Matricula::where('turma_id', $outra->id)->first()?->aluno;
            if ($alunoFora && ! Matricula::where('aluno_id', $alunoFora->id)->whereIn('turma_id', $minhas)->exists()) {
                $this->get(route('alunos.show', $alunoFora))->assertNotFound();
            }
        }
        $this->get(route('alunos.create'))->assertForbidden();
        $this->get(route('professores.index'))->assertForbidden();
    }

    public function test_provincia_consulta_escola_sem_alterar(): void
    {
        $this->entrarComo('provincial@sige.demo.ao');
        $escola = $this->escola('BGO-AMB-0501');
        $this->post(route('contexto.definir'), ['escola_id' => $escola->id]);

        $this->get(route('alunos.index'))->assertOk()->assertDontSee('Cadastrar aluno');
        $this->get(route('turmas.index'))->assertOk();
        $this->get(route('alunos.create'))->assertForbidden();
        $this->post(route('alunos.store'), $this->dadosAluno())->assertForbidden();
        $this->post(route('turmas.store'), [])->assertForbidden();
        $activos = Aluno::semFiltroEscola()->where('escola_id', $escola->id)->where('estado', 'activo')->count();
        $this->get(route('escolas.index', ['q' => 'Horizonte']))->assertOk()->assertSee('>'.$activos.'<', false);
    }

    public function test_configuracao_escola_vs_referencia_provincial(): void
    {
        $this->entrarComo('director@sige.demo.ao');
        $escolaId = $this->escola('BGO-DAN-0205')->id;

        // Disciplina própria da escola (referencia_provincial é ignorada sem "academico.global")
        $esg1 = [\App\Models\NivelEnsino::where('codigo', 'ESG1')->value('id')];
        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Língua Nacional Kimbundu', 'sigla' => 'kimb', 'componente' => 'complementar', 'niveis' => $esg1, 'referencia_provincial' => '1'])
            ->assertForbidden();
        $this->post(route('cadastros.disciplinas.store'), ['nome' => 'Língua Nacional Kimbundu', 'sigla' => 'kimb', 'componente' => 'complementar', 'niveis' => $esg1])
            ->assertSessionHasNoErrors();
        $this->assertSame($escolaId, Disciplina::where('sigla', 'KIMB')->value('escola_id'));

        // Referência provincial: só leitura para o director
        $ref = Disciplina::whereNull('escola_id')->where('sigla', 'LP')->first();
        $this->put(route('cadastros.disciplinas.update', $ref->id), ['nome' => 'X', 'sigla' => 'LP', 'componente' => 'geral', 'niveis' => $esg1])->assertForbidden();
        $this->post(route('cadastros.anos.store'), ['designacao' => '2027/2028', 'data_inicio' => '2027-09-01', 'data_fim' => '2028-07-31', 'estado' => 'planeado'])->assertForbidden();

        // Plano da escola tem prioridade sobre a referência
        $classe = $this->classe('ESG1', 7);
        $kimb = Disciplina::where('sigla', 'KIMB')->value('id');
        $lp = $ref->id;
        $this->post(route('academico.planos.store'), [
            'nome' => 'Plano da escola — 7.ª', 'classe_id' => $classe->id, 'activo' => '1',
            'disciplinas' => [$lp => ['incluir' => 1, 'carga_horaria' => 5], $kimb => ['incluir' => 1, 'carga_horaria' => 2]],
        ])->assertSessionHasNoErrors();
        $plano = PlanoCurricular::resolver($escolaId, $this->ano()->id, $classe->id, null);
        $this->assertSame($escolaId, $plano->escola_id);
        $this->assertEqualsCanonicalizing([$lp, $kimb], $plano->disciplinas->pluck('id')->all());

        // Noutra escola continua a valer a referência provincial
        $this->assertNull(PlanoCurricular::resolver($this->escola('BGO-AMB-0120')->id, $this->ano()->id, $classe->id, null)->escola_id);
    }

    public function test_superutilizador_gere_anos_lectivos(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $this->post(route('cadastros.anos.store'), ['designacao' => '2027/2028', 'data_inicio' => '2027-09-01', 'data_fim' => '2028-07-31', 'estado' => 'planeado'])
            ->assertSessionHasNoErrors();
        $novo = AnoLectivo::where('designacao', '2027/2028')->firstOrFail();
        $this->assertSame(3, $novo->trimestres()->count());

        // Só um ano em curso: é preciso encerrar o actual antes de activar o seguinte.
        $this->patch(route('cadastros.anos.actual', $novo))->assertSessionHas('erro');
        $this->assertFalse($novo->fresh()->actual);
        $actual = AnoLectivo::where('designacao', '2026/2027')->first();
        $this->put(route('cadastros.anos.update', $actual), ['designacao' => '2026/2027', 'data_inicio' => '2026-09-01', 'data_fim' => '2027-07-31', 'estado' => 'encerrado'])
            ->assertSessionHasNoErrors();

        $this->patch(route('cadastros.anos.actual', $novo))->assertSessionHasNoErrors();
        $this->assertSame(1, AnoLectivo::where('actual', true)->count());
        $this->assertTrue($novo->fresh()->actual);
    }

    public function test_documentos_do_aluno_em_disco_privado(): void
    {
        Storage::fake('local');
        $this->entrarComo('secretaria@sige.demo.ao');
        $aluno = Aluno::first();

        $this->post(route('alunos.documentos.store', $aluno), ['tipo' => 'certificado', 'ficheiro' => UploadedFile::fake()->create('cert.pdf', 120, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $doc = $aluno->documentos()->firstOrFail();
        Storage::disk('local')->assertExists($doc->caminho);
        $this->get(route('alunos.documentos.download', [$aluno, $doc]))->assertOk();

        $this->post(route('alunos.documentos.store', $aluno), ['tipo' => 'outro', 'ficheiro' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('ficheiro');
    }
}
