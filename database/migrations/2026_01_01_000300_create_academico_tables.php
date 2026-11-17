<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — Configuração académica, professores, alunos, turmas e matrículas.
 *
 * Catálogos globais (sem escola_id): anos lectivos, trimestres, ciclos, classes, turnos.
 * Globais ou da escola (escola_id opcional): cursos, disciplinas, planos curriculares.
 * Registos operacionais (escola_id obrigatório): professores, alunos, encarregados,
 * documentos, turmas, atribuições e matrículas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveis_ensino', function (Blueprint $table) {
            // Ex.: II Ciclo e Técnico-Profissional exigem curso na matrícula e na turma.
            $table->boolean('exige_curso')->default(false)->after('descricao');
        });

        // Numeração sequencial por escola (processos de alunos, recibos, declarações…)
        Schema::create('sequencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->unsignedSmallInteger('ano')->default(0);
            $table->unsignedInteger('ultimo')->default(0);
            $table->unique(['escola_id', 'tipo', 'ano']);
        });

        Schema::create('anos_lectivos', function (Blueprint $table) {
            $table->id();
            $table->string('designacao', 20)->unique();          // 2026/2027
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->enum('estado', ['planeado', 'em_curso', 'encerrado'])->default('planeado');
            $table->boolean('actual')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('trimestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');                  // 1, 2, 3
            $table->string('designacao', 30);                       // I Trimestre
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->timestamps();
            $table->unique(['ano_lectivo_id', 'numero']);
        });

        Schema::create('ciclos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nivel_ensino_id')->constrained('niveis_ensino')->restrictOnDelete();
            $table->string('nome', 100);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['nivel_ensino_id', 'nome']);
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nivel_ensino_id')->constrained('niveis_ensino')->restrictOnDelete();
            $table->foreignId('ciclo_id')->nullable()->constrained('ciclos')->nullOnDelete();
            $table->string('nome', 60);                              // 7.ª Classe
            $table->unsignedTinyInteger('numero')->nullable();       // 7 (ordenação e regras)
            // Regime de avaliação usado pelo motor de avaliação (Fase 4).
            $table->enum('regime', ['transicao', 'exame_nacional', 'terminal', 'sem_retencao'])->default('transicao');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['nivel_ensino_id', 'nome']);
        });

        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 40)->unique();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->cascadeOnDelete(); // null = referência provincial
            $table->foreignId('nivel_ensino_id')->constrained('niveis_ensino')->restrictOnDelete();
            $table->string('nome', 150);
            $table->string('codigo', 20);
            $table->text('descricao')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->index(['escola_id', 'nivel_ensino_id']);
        });

        Schema::create('disciplinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->string('sigla', 15);
            // Língua Portuguesa e Matemática: restrições especiais nas regras de transição (Fase 4).
            $table->boolean('nuclear')->default(false);
            $table->enum('componente', ['geral', 'especifica', 'tecnica', 'complementar'])->default('geral');
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->index('escola_id');
        });

        Schema::create('planos_curriculares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->nullable()->constrained('anos_lectivos')->restrictOnDelete(); // null = vigente
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->restrictOnDelete();
            $table->string('nome', 150);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['classe_id', 'curso_id']);
            $table->index(['escola_id', 'ano_lectivo_id']);
        });

        Schema::create('plano_curricular_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_curricular_id')->constrained('planos_curriculares')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->unsignedTinyInteger('carga_horaria')->nullable();  // tempos semanais
            $table->boolean('especifica')->default(false);            // disciplina específica da formação
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unique(['plano_curricular_id', 'disciplina_id'], 'plano_disciplina_unica');
        });

        Schema::create('professores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('utilizador_id')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->string('nome', 150);
            $table->enum('genero', ['M', 'F']);
            $table->date('data_nascimento')->nullable();
            $table->string('numero_identificacao', 30);
            $table->string('telefone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->enum('habilitacoes', ['ensino_medio', 'bacharelato', 'licenciatura', 'mestrado', 'doutoramento', 'outra']);
            $table->string('especialidade', 150)->nullable();
            $table->enum('situacao_profissional', ['efectivo', 'contratado', 'colaborador', 'estagiario']);
            $table->date('data_admissao')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
            $table->unique(['escola_id', 'numero_identificacao']);
            $table->unique(['escola_id', 'utilizador_id']);
        });

        Schema::create('encarregados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->string('parentesco', 40)->nullable();
            $table->string('numero_identificacao', 30)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('telefone_alternativo', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('profissao', 100)->nullable();
            $table->string('endereco', 255)->nullable();
            $table->timestamps();
            $table->index('escola_id');
        });

        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('encarregado_id')->nullable()->constrained('encarregados')->nullOnDelete();
            $table->string('numero_processo', 20);
            $table->string('nome', 150);
            $table->enum('genero', ['M', 'F']);
            $table->date('data_nascimento');
            $table->string('naturalidade', 120)->nullable();
            $table->string('nacionalidade', 60)->default('Angolana');
            $table->enum('tipo_documento', ['bi', 'cedula', 'passaporte', 'outro'])->nullable();
            $table->string('numero_identificacao', 30)->nullable();
            $table->string('endereco', 255)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('necessidades_especiais')->nullable();
            $table->enum('estado', ['activo', 'transferido', 'desistente', 'concluido'])->default('activo')->index();
            $table->date('data_estado')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->unique(['escola_id', 'numero_processo']);
            $table->index(['escola_id', 'nome']);
            $table->index(['escola_id', 'genero']);
        });

        Schema::create('aluno_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('descricao', 150)->nullable();
            $table->string('caminho', 255);
            $table->string('nome_original', 200);
            $table->string('mime', 100);
            $table->unsignedInteger('tamanho');
            $table->foreignId('carregado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->restrictOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->foreignId('director_turma_id')->nullable()->constrained('professores')->nullOnDelete();
            $table->string('nome', 30);
            $table->string('sala', 30)->nullable();
            $table->unsignedSmallInteger('capacidade')->default(40);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['escola_id', 'ano_lectivo_id', 'classe_id', 'nome']);
            $table->index(['escola_id', 'ano_lectivo_id']);
        });

        Schema::create('atribuicoes_professor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('professor_id')->constrained('professores')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['turma_id', 'disciplina_id']);   // uma disciplina por turma tem um professor
            $table->index('professor_id');
        });

        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->restrictOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('turno_id')->constrained('turnos')->restrictOnDelete();
            $table->string('numero', 20);
            $table->enum('tipo', ['nova', 'confirmacao', 'transferencia'])->default('nova');
            $table->date('data_matricula');
            $table->enum('estado', ['activa', 'transferida', 'desistente', 'concluida', 'anulada'])->default('activa')->index();
            $table->text('observacoes')->nullable();
            $table->foreignId('registado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
            // Um aluno não pode ter duas matrículas no mesmo ano lectivo.
            $table->unique(['aluno_id', 'ano_lectivo_id']);
            $table->unique(['escola_id', 'numero']);
            $table->index(['escola_id', 'ano_lectivo_id', 'classe_id']);
            $table->index('turma_id');
        });
    }

    public function down(): void
    {
        foreach (['matriculas', 'atribuicoes_professor', 'turmas', 'aluno_documentos', 'alunos', 'encarregados', 'professores',
            'plano_curricular_disciplina', 'planos_curriculares', 'disciplinas', 'cursos', 'turnos', 'classes', 'ciclos',
            'trimestres', 'anos_lectivos', 'sequencias'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('niveis_ensino', fn (Blueprint $table) => $table->dropColumn('exige_curso'));
    }
};
