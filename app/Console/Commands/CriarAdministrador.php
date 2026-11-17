<?php

namespace App\Console\Commands;

use App\Models\Perfil;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Cria o primeiro superutilizador numa instalação nova (produção, sem dados demonstrativos). */
class CriarAdministrador extends Command
{
    protected $signature = 'sige:criar-administrador';

    protected $description = 'Cria uma conta de superutilizador (pede nome, e-mail e senha)';

    public function handle(): int
    {
        $perfil = Perfil::where('codigo', Perfil::SUPERUTILIZADOR)->first();
        if (! $perfil) {
            $this->error('Os perfis ainda não existem. Execute: php artisan db:seed --class=PerfisPermissoesSeeder');

            return self::FAILURE;
        }

        $dados = [
            'nome' => $this->ask('Nome completo'),
            'email' => $this->ask('E-mail'),
            'password' => $this->secret('Senha (mínimo 8 caracteres, com letras e números)'),
        ];
        $v = Validator::make($dados, [
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:utilizadores,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        User::create($dados + ['perfil_id' => $perfil->id, 'activo' => true]);
        $this->info("Superutilizador {$dados['email']} criado.");

        return self::SUCCESS;
    }
}
