<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Constrói o menu lateral filtrado pelas permissões do utilizador.
 */
class Menu
{
    public static function paraUtilizador(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $seccoes = [];
        foreach (config('menu') as $seccao) {
            $itens = [];
            foreach ($seccao['itens'] as $item) {
                if (! empty($item['permissao']) && ! static::temAlguma($user, $item['permissao'])) {
                    continue;
                }

                $emDesenvolvimento = $item['em_desenvolvimento'] ?? null;
                $item['url'] = $emDesenvolvimento
                    ? route('em-desenvolvimento', ['modulo' => Str::slug($item['rotulo'])])
                    : route($item['rota']);
                $item['actual'] = $emDesenvolvimento
                    ? request()->routeIs('em-desenvolvimento') && request()->route('modulo') === Str::slug($item['rotulo'])
                    : Route::is(...explode('|', $item['activo'] ?? $item['rota']));
                $itens[] = $item;
            }
            if ($itens) {
                $seccoes[] = ['titulo' => $seccao['titulo'], 'itens' => $itens];
            }
        }

        return $seccoes;
    }

    /** Procura um item do menu pelo slug (usado na página "em desenvolvimento"). */
    public static function itemPorSlug(string $slug): ?array
    {
        foreach (config('menu') as $seccao) {
            foreach ($seccao['itens'] as $item) {
                if (Str::slug($item['rotulo']) === $slug) {
                    return $item + ['seccao' => $seccao['titulo']];
                }
            }
        }

        return null;
    }

    public static function temAlguma(User $user, string $permissoes): bool
    {
        foreach (explode('|', $permissoes) as $p) {
            if ($user->temPermissao(trim($p))) {
                return true;
            }
        }

        return false;
    }
}
