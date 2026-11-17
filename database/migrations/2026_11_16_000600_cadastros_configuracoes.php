<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo "Cadastros e Configurações".
 *
 * Reutiliza as tabelas existentes (municípios, escolas, níveis, ciclos, classes, disciplinas,
 * planos curriculares, anos lectivos e trimestres) e acrescenta apenas:
 *  - escolas.activo: desactivação lógica do registo (independente do estado de funcionamento);
 *  - disciplina_nivel_ensino: níveis de ensino a que cada disciplina se aplica;
 *  - plano_curricular_disciplina.obrigatoria: disciplina obrigatória ou opcional na classe.
 *
 * Preserva todos os dados: a associação disciplina ↔ nível é preenchida a partir dos
 * planos curriculares já existentes, e as novas permissões são acrescentadas aos perfis
 * do sistema sem retirar as que o superutilizador tenha configurado.
 */
return new class extends Migration
{
    /** Permissões acrescentadas a cada perfil do sistema (apenas acrescentar, nunca retirar). */
    private const NOVAS_PERMISSOES = [
        'direccao_provincial' => ['cadastros.ver', 'municipios.gerir', 'escolas.gerir', 'rede.catalogos', 'academico.global'],
        'direccao_municipal' => ['cadastros.ver', 'escolas.gerir'],
        'director_escola' => ['cadastros.ver'],
        'professor' => ['cadastros.ver'],
        'secretaria' => ['cadastros.ver'],
    ];

    public function up(): void
    {
        Schema::table('escolas', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('estado_funcionamento')->index();
        });

        Schema::create('disciplina_nivel_ensino', function (Blueprint $table) {
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('nivel_ensino_id')->constrained('niveis_ensino')->restrictOnDelete();
            $table->primary(['disciplina_id', 'nivel_ensino_id']);
            $table->index('nivel_ensino_id');
        });

        Schema::table('plano_curricular_disciplina', function (Blueprint $table) {
            $table->boolean('obrigatoria')->default(true)->after('especifica');
        });

        // Níveis de cada disciplina deduzidos dos planos curriculares já cadastrados.
        DB::statement('INSERT IGNORE INTO disciplina_nivel_ensino (disciplina_id, nivel_ensino_id)
            SELECT DISTINCT pcd.disciplina_id, c.nivel_ensino_id
            FROM plano_curricular_disciplina pcd
            JOIN planos_curriculares p ON p.id = pcd.plano_curricular_id
            JOIN classes c ON c.id = p.classe_id');

        $this->actualizarPermissoes();
    }

    private function actualizarPermissoes(): void
    {
        $catalogo = config('permissoes.catalogo');
        foreach ($catalogo as $modulo => $permissoes) {
            foreach ($permissoes as $codigo => $nome) {
                $existe = DB::table('permissoes')->where('codigo', $codigo)->exists();
                $existe
                    ? DB::table('permissoes')->where('codigo', $codigo)->update(['nome' => $nome, 'modulo' => $modulo, 'updated_at' => now()])
                    : DB::table('permissoes')->insert(['codigo' => $codigo, 'nome' => $nome, 'modulo' => $modulo, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $ids = DB::table('permissoes')->pluck('id', 'codigo');
        foreach (self::NOVAS_PERMISSOES as $perfil => $codigos) {
            $perfilId = DB::table('perfis')->where('codigo', $perfil)->value('id');
            if (! $perfilId) {
                continue;
            }
            foreach ($codigos as $codigo) {
                DB::table('perfil_permissao')->insertOrIgnore(['perfil_id' => $perfilId, 'permissao_id' => $ids[$codigo]]);
            }
        }

        // O superutilizador tem todas as permissões.
        $super = DB::table('perfis')->where('codigo', 'superutilizador')->value('id');
        if ($super) {
            foreach ($ids as $id) {
                DB::table('perfil_permissao')->insertOrIgnore(['perfil_id' => $super, 'permissao_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('permissoes')->where('codigo', 'cadastros.ver')->value('id');
        if ($id) {
            DB::table('perfil_permissao')->where('permissao_id', $id)->delete();
            DB::table('permissoes')->where('id', $id)->delete();
        }
        Schema::table('plano_curricular_disciplina', fn (Blueprint $t) => $t->dropColumn('obrigatoria'));
        Schema::dropIfExists('disciplina_nivel_ensino');
        Schema::table('escolas', function (Blueprint $t) {
            $t->dropIndex(['activo']);
            $t->dropColumn('activo');
        });
    }
};
