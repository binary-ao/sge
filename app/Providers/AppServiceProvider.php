<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use App\Support\Menu;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uma instância por pedido HTTP.
        $this->app->scoped(ContextoEscola::class);
        $this->app->scoped(\App\Services\Avaliacao\ServicoConfiguracao::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        \Illuminate\Support\Carbon::setLocale(config('app.locale', 'pt'));

        $this->registarAutorizacao();
        $this->registarEventosAutenticacao();

        // Módulos operacionais: a escola activa está sempre disponível nas vistas.
        View::composer(['escola.*', 'avaliacao.*', 'administrativo.*'], function ($view) {
            $view->with('escolaActiva', app(ContextoEscola::class)->escolaActiva());
        });

        View::composer('layouts.app', function ($view) {
            $contexto = app(ContextoEscola::class);
            $view->with([
                'contexto' => $contexto,
                'escolaActiva' => $contexto->escolaActiva(),
                'menu' => Menu::paraUtilizador(auth()->user()),
            ]);
        });
    }

    /**
     * As permissões ("modulo.accao") são verificadas a partir da base de dados.
     * O superutilizador tem acesso global. Abilities sem ponto ficam para as Policies.
     */
    protected function registarAutorizacao(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperutilizador()) {
                return true;
            }
            if (str_contains($ability, '.')) {
                return $user->temPermissao($ability) ?: null;
            }

            return null;
        });
    }

    protected function registarEventosAutenticacao(): void
    {
        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();
            app(ContextoEscola::class)->limparCache();
            Auditoria::registar('login', 'Entrada no sistema', null, [], $e->user->id);
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                Auditoria::registar('logout', 'Saída do sistema', null, [], $e->user->id);
            }
        });

        Event::listen(Failed::class, function (Failed $e) {
            Auditoria::registar('login_falhado', 'Tentativa de entrada falhada: '.mb_substr((string) ($e->credentials['email'] ?? ''), 0, 150),
                null, [], $e->user?->id);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $e) {
            Auditoria::registar('senha', 'Senha redefinida através de recuperação', null, [], $e->user->id);
        });
    }
}
