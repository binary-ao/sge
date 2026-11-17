<?php

namespace Database\Factories;

use App\Models\Perfil;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'senha-teste-123',
            'perfil_id' => fn () => Perfil::where('codigo', Perfil::DIRECTOR_ESCOLA)->value('id'),
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function perfil(string $codigo): static
    {
        return $this->state(fn () => ['perfil_id' => Perfil::where('codigo', $codigo)->value('id')]);
    }
}
