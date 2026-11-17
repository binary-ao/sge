<?php

namespace Database\Seeders;

use App\Models\Perfil;
use App\Models\Permissao;
use Illuminate\Database\Seeder;

class PerfisPermissoesSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('permissoes');

        foreach ($config['catalogo'] as $modulo => $permissoes) {
            foreach ($permissoes as $codigo => $nome) {
                Permissao::updateOrCreate(['codigo' => $codigo], ['nome' => $nome, 'modulo' => $modulo]);
            }
        }

        foreach ($config['perfis'] as $codigo => $dados) {
            $perfil = Perfil::updateOrCreate(['codigo' => $codigo], [
                'nome' => $dados['nome'],
                'ambito' => $dados['ambito'],
                'descricao' => $dados['descricao'],
                'sistema' => true,
            ]);

            $ids = $dados['permissoes'] === '*'
                ? Permissao::pluck('id')
                : Permissao::whereIn('codigo', $dados['permissoes'])->pluck('id');

            $perfil->permissoes()->sync($ids);
        }
    }
}
