<?php

namespace Tests;

use App\Models\Escola;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Todas as funcionalidades são testadas sobre os dados de base e demonstrativos. */
    protected bool $seed = true;

    protected function utilizador(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function entrarComo(string $email): User
    {
        $user = $this->utilizador($email);
        $this->actingAs($user);

        return $user;
    }

    protected function escola(string $codigo): Escola
    {
        return Escola::where('codigo', $codigo)->firstOrFail();
    }
}
