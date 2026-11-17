<?php

namespace App\Models\Concerns;

use App\Support\ContextoEscola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Isolamento multi-escola para registos operacionais.
 *
 * - Todas as consultas são filtradas pela escola activa do utilizador
 *   (ou pelas escolas do seu âmbito, se não houver escola activa).
 * - Um registo de outra escola não é encontrado: route model binding devolve 404.
 * - Na criação, o escola_id é sempre o da escola activa; não é aceite do formulário.
 *
 * Sem utilizador autenticado (consola, seeders) não há filtro.
 */
trait PertenceAEscola
{
    public static function bootPertenceAEscola(): void
    {
        static::addGlobalScope('escola', function (Builder $q) {
            if (! Auth::hasUser()) {
                return;
            }
            $ctx = app(ContextoEscola::class);
            $coluna = $q->getModel()->qualifyColumn('escola_id');

            if ($id = $ctx->escolaActivaId()) {
                $q->where($coluna, $id);
            } elseif (($ids = $ctx->idsEscolasVisiveis()) !== null) {
                $q->whereIn($coluna, $ids);
            }
        });

        static::creating(function (Model $m) {
            if (Auth::hasUser()) {
                $id = app(ContextoEscola::class)->escolaActivaId();
                if (! $id) {
                    throw new RuntimeException('Não existe escola activa para registar '.class_basename($m).'.');
                }
                $m->escola_id = $id;
            } elseif (! $m->escola_id) {
                throw new RuntimeException('escola_id em falta para '.class_basename($m).'.');
            }
        });

        // escola_id nunca muda depois de criado.
        static::updating(function (Model $m) {
            if ($m->isDirty('escola_id')) {
                throw new RuntimeException('Não é permitido transferir registos entre escolas por esta via.');
            }
        });
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola::class);
    }

    /** Ignora o filtro de escola (usar apenas em agregações já restringidas ao âmbito). */
    public static function semFiltroEscola(): Builder
    {
        return static::withoutGlobalScope('escola');
    }
}
