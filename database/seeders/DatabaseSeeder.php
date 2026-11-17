<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados de base + dados DEMONSTRATIVOS (não oficiais).
     */
    public function run(): void
    {
        $this->call([
            PerfisPermissoesSeeder::class,
            RedeEscolarSeeder::class,
            UtilizadoresDemoSeeder::class,
            ConfiguracaoAcademicaSeeder::class,
            DemoAcademicoSeeder::class,
            AvaliacaoSeeder::class,
            DemoAvaliacoesSeeder::class,
            DemoAdministrativoSeeder::class,
        ]);
    }
}
