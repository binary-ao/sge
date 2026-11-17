<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 — Faltas e justificativos, serviços e pagamentos, recibos e declarações.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faltas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('professor_id')->nullable()->constrained('professores')->nullOnDelete();
            $table->date('data');
            $table->unsignedTinyInteger('tempos')->default(1);          // tempos lectivos em falta
            $table->string('motivo', 255)->nullable();
            // Estado da justificação: sem justificativo, ou o estado do justificativo submetido.
            $table->enum('justificacao', ['nao_justificada', 'pendente', 'aprovado', 'rejeitado'])->default('nao_justificada')->index();
            $table->foreignId('registado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
            $table->unique(['matricula_id', 'disciplina_id', 'data']);
            $table->index(['turma_id', 'data']);
        });

        Schema::create('justificativos_falta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('falta_id')->constrained('faltas')->cascadeOnDelete();
            $table->text('motivo');
            $table->string('documento', 255)->nullable();
            $table->string('documento_nome', 200)->nullable();
            $table->enum('estado', ['pendente', 'aprovado', 'rejeitado'])->default('pendente')->index();
            $table->foreignId('submetido_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->foreignId('analisado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamp('analisado_em')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('servicos_pagaveis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete(); // null = todas as classes
            $table->enum('tipo', ['matricula', 'mensalidade', 'declaracao', 'outro']);
            $table->string('nome', 150);
            $table->decimal('valor', 12, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['escola_id', 'tipo']);
        });

        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->string('numero', 30);
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('sequencial');
            $table->date('data');
            $table->decimal('total', 12, 2);
            $table->enum('forma_pagamento', ['numerario', 'transferencia', 'tpa']);
            $table->string('referencia', 60)->nullable();
            $table->enum('estado', ['emitido', 'anulado'])->default('emitido')->index();
            $table->foreignId('emitido_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamp('anulado_em')->nullable();
            $table->string('motivo_anulacao', 500)->nullable();
            $table->timestamps();
            $table->unique(['escola_id', 'numero']);
            $table->unique(['escola_id', 'ano', 'sequencial']);
        });

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('recibo_id')->constrained('recibos')->restrictOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->foreignId('servico_id')->constrained('servicos_pagaveis')->restrictOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->unsignedTinyInteger('mes')->nullable();          // 1–12 (mensalidades)
            $table->unsignedSmallInteger('ano_mes')->nullable();     // ano civil do mês pago
            $table->string('descricao', 200);
            $table->decimal('valor', 12, 2);
            $table->date('data_pagamento');
            $table->enum('forma_pagamento', ['numerario', 'transferencia', 'tpa']);
            $table->enum('estado', ['valido', 'anulado'])->default('valido')->index();
            $table->foreignId('registado_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
            $table->index(['aluno_id', 'servico_id', 'ano_mes', 'mes']);
            $table->index(['escola_id', 'data_pagamento']);
        });

        Schema::create('declaracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
            $table->enum('tipo', ['matricula', 'frequencia', 'notas']);
            $table->string('numero', 30);
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('sequencial');
            $table->string('finalidade', 255)->nullable();
            $table->json('conteudo');                                // dados usados (reimpressão idêntica)
            $table->string('codigo_verificacao', 12)->unique();
            $table->foreignId('emitida_por')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->timestamps();
            $table->unique(['escola_id', 'numero']);
        });
    }

    public function down(): void
    {
        foreach (['declaracoes', 'pagamentos', 'recibos', 'servicos_pagaveis', 'justificativos_falta', 'faltas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
