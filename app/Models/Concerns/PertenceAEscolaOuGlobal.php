<?php

namespace App\Models\Concerns;

use App\Support\ContextoEscola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Registos de configuração que podem ser de referência provincial (escola_id = null)
 * ou próprios de uma escola. Cada escola vê os de referência e os seus.
 */
trait PertenceAEscolaOuGlobal
{
    public static function bootPertenceAEscolaOuGlobal(): void
    {
        static::addGlobalScope('escola', function (Builder $q) {
            if (! Auth::hasUser()) {
                return;
            }
            $ctx = app(ContextoEscola::class);
            $coluna = $q->getModel()->qualifyColumn('escola_id');
            $id = $ctx->escolaActivaId();
            $ids = $id ? [$id] : $ctx->idsEscolasVisiveis();

            if ($ids !== null) {
                $q->where(fn ($w) => $w->whereNull($coluna)->orWhereIn($coluna, $ids));
            }
        });
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Escola::class);
    }

    public function eReferenciaProvincial(): bool
    {
        return $this->escola_id === null;
    }

    public function scopeDaEscolaOuGlobal(Builder $q, ?int $escolaId): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('escola_id')->when($escolaId, fn ($x) => $x->orWhere('escola_id', $escolaId)));
    }
}
