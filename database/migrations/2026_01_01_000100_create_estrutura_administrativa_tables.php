<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura administrativa: Província → Município → Escola,
 * com tipologia institucional e níveis de ensino como catálogos independentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->string('codigo', 10)->unique();
            $table->string('capital', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained('provincias')->restrictOnDelete();
            $table->string('nome', 100);
            $table->string('codigo', 20)->unique();
            $table->string('sede', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(['provincia_id', 'nome']);
        });

        Schema::create('tipologias_escola', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            $table->string('sigla', 20)->nullable();
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('niveis_ensino', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120)->unique();
            $table->string('codigo', 30)->unique();
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('escolas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->restrictOnDelete();
            $table->foreignId('tipologia_id')->constrained('tipologias_escola')->restrictOnDelete();
            $table->string('nome', 200);
            $table->string('codigo', 30)->unique();
            $table->enum('natureza', ['publica', 'publico_privada', 'privada'])->index();
            // Localização
            $table->string('comuna', 100)->nullable();
            $table->string('bairro', 150)->nullable();
            $table->string('endereco', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('coordenadas_demonstrativas')->default(false);
            // Contactos
            $table->string('telefone', 30)->nullable();
            $table->string('telefone_alternativo', 30)->nullable();
            $table->string('email', 150)->nullable();
            // Direcção
            $table->string('director_nome', 150)->nullable();
            $table->string('director_telefone', 30)->nullable();
            // Funcionamento
            $table->enum('estado_funcionamento', ['em_funcionamento', 'temporariamente_encerrada', 'encerrada', 'em_construcao'])
                ->default('em_funcionamento')->index();
            $table->unsignedSmallInteger('ano_inicio_actividade')->nullable();
            $table->unsignedSmallInteger('numero_salas')->nullable();
            // Campos configuráveis para necessidades futuras
            $table->json('dados_adicionais')->nullable();
            $table->timestamps();

            $table->index(['municipio_id', 'tipologia_id']);
        });

        Schema::create('escola_nivel_ensino', function (Blueprint $table) {
            $table->foreignId('escola_id')->constrained('escolas')->cascadeOnDelete();
            $table->foreignId('nivel_ensino_id')->constrained('niveis_ensino')->restrictOnDelete();
            $table->primary(['escola_id', 'nivel_ensino_id']);
            $table->index('nivel_ensino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escola_nivel_ensino');
        Schema::dropIfExists('escolas');
        Schema::dropIfExists('niveis_ensino');
        Schema::dropIfExists('tipologias_escola');
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('provincias');
    }
};
