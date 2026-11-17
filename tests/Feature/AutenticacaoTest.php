<?php

namespace Tests\Feature;

use App\Models\LogActividade;
use App\Notifications\RedefinirSenhaNotification;
use Database\Seeders\UtilizadoresDemoSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    public function test_visitante_e_redireccionado_para_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Entrar');
    }

    public function test_login_com_credenciais_validas_regista_actividade(): void
    {
        $this->post(route('login'), ['email' => 'provincial@sige.demo.ao', 'password' => UtilizadoresDemoSeeder::SENHA])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('logs_actividade', ['accao' => 'login']);
        $this->assertNotNull($this->utilizador('provincial@sige.demo.ao')->ultimo_acesso_em);
    }

    public function test_login_com_senha_errada_falha(): void
    {
        $this->from(route('login'))
            ->post(route('login'), ['email' => 'provincial@sige.demo.ao', 'password' => 'errada'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(LogActividade::where('accao', 'login_falhado')->exists());
    }

    public function test_conta_desactivada_nao_entra(): void
    {
        $this->utilizador('director@sige.demo.ao')->update(['activo' => false]);

        $this->post(route('login'), ['email' => 'director@sige.demo.ao', 'password' => UtilizadoresDemoSeeder::SENHA])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_utilizador_desactivado_durante_sessao_e_expulso(): void
    {
        $user = $this->entrarComo('director@sige.demo.ao');
        $user->update(['activo' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_limite_de_tentativas_de_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => 'admin@sige.demo.ao', 'password' => 'errada']);
        }
        $this->post(route('login'), ['email' => 'admin@sige.demo.ao', 'password' => UtilizadoresDemoSeeder::SENHA])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_recuperacao_de_senha_completa(): void
    {
        Notification::fake();
        $user = $this->utilizador('secretaria@sige.demo.ao');

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, RedefinirSenhaNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'NovaSenha2026', 'password_confirmation' => 'NovaSenha2026',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'NovaSenha2026'])->assertRedirect(route('inicio'));
    }

    public function test_recuperacao_nao_revela_emails_inexistentes(): void
    {
        Notification::fake();
        $this->post(route('password.email'), ['email' => 'naoexiste@exemplo.ao'])
            ->assertSessionHas('status', __('passwords.sent'));
        Notification::assertNothingSent();
    }

    public function test_logout(): void
    {
        $this->entrarComo('admin@sige.demo.ao');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_alterar_propria_senha_exige_senha_actual(): void
    {
        $this->entrarComo('professor@sige.demo.ao');

        $this->put(route('conta.senha'), ['senha_actual' => 'errada', 'password' => 'Outra2026x', 'password_confirmation' => 'Outra2026x'])
            ->assertSessionHasErrors('senha_actual');

        $this->put(route('conta.senha'), ['senha_actual' => UtilizadoresDemoSeeder::SENHA, 'password' => 'Outra2026x', 'password_confirmation' => 'Outra2026x'])
            ->assertSessionHasNoErrors();
    }
}
