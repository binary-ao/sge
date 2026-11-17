<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Utilizadores, perfis, permissões, atribuição a escolas/municípios e registo de actividades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            // Âmbito de acesso aos dados: global, provincia, municipio, escola
            $table->enum('ambito', ['global', 'provincia', 'municipio', 'escola']);
            $table->boolean('sistema')->default(true); // perfis base não podem ser eliminados
            $table->timestamps();
        });

        Schema::create('permissoes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nome', 150);
            $table->string('modulo', 80)->index();
            $table->timestamps();
        });

        Schema::create('perfil_permissao', function (Blueprint $table) {
            $table->foreignId('perfil_id')->constrained('perfis')->cascadeOnDelete();
            $table->foreignId('permissao_id')->constrained('permissoes')->cascadeOnDelete();
            $table->primary(['perfil_id', 'permissao_id']);
        });

        Schema::create('utilizadores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->string('email', 150)->unique();
            $table->string('telefone', 30)->nullable();
            $table->string('password');
            $table->foreignId('perfil_id')->constrained('perfis')->restrictOnDelete();
            // Âmbito municipal (Direcção Municipal)
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->timestamp('ultimo_acesso_em')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Atribuição de utilizadores às escolas (um professor pode leccionar em mais de uma)
        Schema::create('utilizador_escola', function (Blueprint $table) {
            $table->foreignId('utilizador_id')->constrained('utilizadores')->cascadeOnDelete();
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->primary(['utilizador_id', 'escola_id']);
            $table->index('escola_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('logs_actividade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilizador_id')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->foreignId('escola_id')->nullable()->constrained('escolas')->nullOnDelete();
            $table->string('accao', 50)->index();          // login, criar, actualizar, …
            $table->string('descricao', 255);
            $table->string('modelo_tipo', 100)->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->json('dados')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['modelo_tipo', 'modelo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_actividade');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('utilizador_escola');
        Schema::dropIfExists('utilizadores');
        Schema::dropIfExists('perfil_permissao');
        Schema::dropIfExists('permissoes');
        Schema::dropIfExists('perfis');
    }
};
