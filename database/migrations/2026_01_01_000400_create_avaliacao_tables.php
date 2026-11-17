<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — Motor de avaliação configurável (referência: Decreto Executivo n.º 424/25).
 *
 * Configuração (global):  escalas → intervalos; sistemas → configurações → componentes, fórmulas, regras;
 *                         modelos de fórmula (Anexo III); histórico de versões.
 * Operação (por escola):  períodos de lançamento (fecho/reabertura), avaliações (instrumentos),
 *                         notas, resultados por disciplina e resultados finais do aluno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalas_classificacao', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150)->unique();
            $table->text('descricao')->nullable();
            $table->decimal('nota_minima', 5, 2)->default(0);
            $table->decimal('nota_maxima', 5, 2);
            $table->unsignedInteger('versao')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('intervalos_classificacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escala_id')->constrained('escalas_classificacao')->cascadeOnDelete();
            $table->decimal('minimo', 5, 2);
            $table->decimal('maximo', 5, 2);
            $table->string('rotulo', 60);                     // Excelente, Bom…
            $table->string('sigla', 10)->nullable();
            // Valor numérico usado quando a classificação é lançada de forma qualitativa.
            $table->decimal('valor_referencia', 5, 2);
            $table->unsignedSmallInteger('ordem')->default(0);
        });

        // Biblioteca de modelos de fórmula (Anexo III), seleccionáveis, editáveis e versionados.
        Schema::create('modelos_formula', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 20)->unique();        // A, B, C… (Anexo III)
            $table->string('codigo', 20);                     // MACT, MT, MFD…
            $table->string('nome', 150);
            $table->string('expressao', 500);
            $table->enum('nivel', ['trimestral', 'anual']);
            $table->text('descricao')->nullable();
            $table->string('aplicacao', 255)->nullable();     // em que classes / modalidades se aplica
            $table->unsignedInteger('versao')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('sistemas_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150)->unique();
            $table->text('descricao')->nullable();
            $table->string('base_legal', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('configuracoes_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sistema_avaliacao_id')->constrained('sistemas_avaliacao')->restrictOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->foreignId('nivel_ensino_id')->constrained('niveis_ensino')->restrictOnDelete();
            $table->foreignId('ciclo_id')->nullable()->constrained('ciclos')->nullOnDelete();
            // Configuração específica de uma disciplina (ex.: exame combinado); null = todas.
            $table->foreignId('disciplina_id')->nullable()->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('escala_id')->constrained('escalas_classificacao')->restrictOnDelete();
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->enum('tipo_classificacao', ['quantitativa', 'qualitativa', 'mista']);
            $table->decimal('nota_minima_aprovacao', 5, 2);
            $table->enum('arredondamento', ['unidade', 'decimas', 'nenhum'])->default('unidade');
            // Variáveis que dão a classificação do trimestre (ex.: MT → MT1, MT2, MT3) e a final (ex.: MFD).
            $table->string('variavel_trimestral', 20);
            $table->string('variavel_final', 20);
            $table->enum('estado', ['rascunho', 'activa', 'inactiva'])->default('rascunho')->index();
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('criado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
            $table->index(['ano_lectivo_id', 'estado']);
        });

        Schema::create('configuracao_avaliacao_classe', function (Blueprint $table) {
            $table->foreignId('configuracao_avaliacao_id')->constrained('configuracoes_avaliacao')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->primary(['configuracao_avaliacao_id', 'classe_id'], 'config_classe_pk');
            $table->index('classe_id');
        });

        Schema::create('componentes_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuracao_avaliacao_id')->constrained('configuracoes_avaliacao')->cascadeOnDelete();
            $table->string('codigo', 20);                     // AC, NPT, NEE, NEO, NEP, EN
            $table->string('nome', 150);
            $table->enum('tipo', ['lista', 'valor']);         // lista = várias avaliações (AC1, AC2…)
            $table->enum('momento', ['trimestral', 'exame']);
            $table->string('trimestres', 10)->nullable();     // "1,2,3"; null = todos (só para momento trimestral)
            $table->unsignedTinyInteger('itens_previstos')->default(1);
            $table->decimal('nota_minima', 5, 2)->default(0);
            $table->decimal('nota_maxima', 5, 2);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unique(['configuracao_avaliacao_id', 'codigo'], 'componente_codigo_unico');
        });

        Schema::create('formulas_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuracao_avaliacao_id')->constrained('configuracoes_avaliacao')->cascadeOnDelete();
            $table->foreignId('modelo_formula_id')->nullable()->constrained('modelos_formula')->nullOnDelete();
            $table->string('codigo', 20);
            $table->string('nome', 150);
            $table->string('expressao', 500);
            $table->enum('nivel', ['trimestral', 'anual']);
            $table->string('trimestres', 10)->nullable();
            $table->boolean('arredondar')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unique(['configuracao_avaliacao_id', 'codigo'], 'formula_codigo_unico');
        });

        // Regras de aprovação, transição, frequência e acesso a exames (parametrizadas).
        Schema::create('regras_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configuracao_avaliacao_id')->constrained('configuracoes_avaliacao')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->json('parametros')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
        });

        Schema::create('historico_configuracao', function (Blueprint $table) {
            $table->id();
            $table->string('entidade', 40);                   // configuracao, escala, modelo_formula
            $table->unsignedBigInteger('entidade_id');
            $table->unsignedInteger('versao');
            $table->string('accao', 30);                      // criacao, alteracao, duplicacao, activacao…
            $table->text('motivo')->nullable();
            $table->json('snapshot');
            $table->foreignId('utilizador_id')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entidade', 'entidade_id', 'versao']);
        });

        // Estado de lançamento por escola: períodos 1–3 (trimestres) e 4 (exames).
        Schema::create('periodos_lancamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->unsignedTinyInteger('periodo');
            $table->enum('estado', ['aberto', 'fechado'])->default('aberto');
            $table->foreignId('alterado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamp('alterado_em')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();
            $table->unique(['escola_id', 'ano_lectivo_id', 'periodo']);
        });

        // Instrumentos de avaliação (colunas da grelha): AC1 "Teste", AC2 "Trabalho", NPT…
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->unsignedTinyInteger('periodo');           // 1–3 trimestres, 4 = exames
            $table->string('componente', 20);
            $table->unsignedTinyInteger('indice')->default(1);
            $table->string('descricao', 100)->nullable();
            $table->date('data')->nullable();
            $table->timestamps();
            $table->unique(['turma_id', 'disciplina_id', 'periodo', 'componente', 'indice'], 'avaliacao_unica');
        });

        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('avaliacao_id')->constrained('avaliacoes')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->decimal('valor', 5, 2);
            $table->foreignId('lancado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
            $table->unique(['avaliacao_id', 'matricula_id']);
            $table->index('matricula_id');
        });

        Schema::create('resultados_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->unsignedTinyInteger('periodo');           // 1–3 trimestre, 0 = classificação final
            $table->json('valores')->nullable();              // todas as variáveis calculadas
            $table->decimal('classificacao', 5, 2)->nullable();
            $table->string('classificacao_qualitativa', 60)->nullable();
            $table->enum('situacao', ['positiva', 'negativa', 'incompleta']);
            $table->foreignId('configuracao_avaliacao_id')->nullable()->constrained('configuracoes_avaliacao')->nullOnDelete();
            $table->unsignedInteger('configuracao_versao')->nullable();
            $table->timestamp('calculado_em');
            $table->foreignId('calculado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->unique(['matricula_id', 'disciplina_id', 'periodo'], 'resultado_unico');
            $table->index(['turma_id', 'disciplina_id', 'periodo']);
        });

        Schema::create('resultados_finais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('matricula_id')->unique()->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->string('situacao', 40)->index();
            $table->string('rotulo', 80);
            $table->json('justificacao');
            $table->decimal('media', 5, 2)->nullable();       // média informativa das classificações finais
            $table->unsignedTinyInteger('disciplinas_negativas')->default(0);
            $table->foreignId('configuracao_avaliacao_id')->nullable()->constrained('configuracoes_avaliacao')->nullOnDelete();
            $table->unsignedInteger('configuracao_versao')->nullable();
            $table->timestamp('calculado_em');
            $table->index(['escola_id', 'ano_lectivo_id', 'situacao']);
        });
    }

    public function down(): void
    {
        foreach (['resultados_finais', 'resultados_avaliacao', 'notas', 'avaliacoes', 'periodos_lancamento', 'historico_configuracao',
            'regras_avaliacao', 'formulas_avaliacao', 'componentes_avaliacao', 'configuracao_avaliacao_classe',
            'configuracoes_avaliacao', 'sistemas_avaliacao', 'modelos_formula', 'intervalos_classificacao', 'escalas_classificacao'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
