<?php

namespace Database\Seeders;

use App\Models\Escola;
use App\Models\Municipio;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contas demonstrativas para todos os perfis.
 * As credenciais estão documentadas em CREDENCIAIS_DEMO.md.
 */
class UtilizadoresDemoSeeder extends Seeder
{
    public const SENHA = 'Bengo@2026';

    public function run(): void
    {
        $perfil = fn (string $c) => Perfil::where('codigo', $c)->value('id');
        $dande = Municipio::where('codigo', 'BGO-DAN')->first();
        $complexo = Escola::where('codigo', 'BGO-DAN-0205')->first();
        $liceu = Escola::where('codigo', 'BGO-DAN-0310')->first();

        $contas = [
            ['admin@sige.demo.ao', 'Administrador do Sistema', Perfil::SUPERUTILIZADOR, null, []],
            ['provincial@sige.demo.ao', 'Técnico da Direcção Provincial', Perfil::DIRECCAO_PROVINCIAL, null, []],
            ['municipal.dande@sige.demo.ao', 'Técnico da Direcção Municipal do Dande', Perfil::DIRECCAO_MUNICIPAL, $dande?->id, []],
            ['director@sige.demo.ao', 'Director do Complexo Escolar n.º 205', Perfil::DIRECTOR_ESCOLA, null, [$complexo?->id]],
            ['professor@sige.demo.ao', 'Professor de Matemática (demo)', Perfil::PROFESSOR, null, [$complexo?->id, $liceu?->id]],
            ['secretaria@sige.demo.ao', 'Secretaria do Complexo Escolar n.º 205', Perfil::SECRETARIA, null, [$complexo?->id]],
        ];

        foreach ($contas as [$email, $nome, $codigoPerfil, $municipioId, $escolas]) {
            $user = User::updateOrCreate(['email' => $email], [
                'nome' => $nome,
                'password' => self::SENHA,
                'perfil_id' => $perfil($codigoPerfil),
                'municipio_id' => $municipioId,
                'activo' => true,
            ]);
            $user->escolas()->sync(array_filter($escolas));
        }
    }
}
