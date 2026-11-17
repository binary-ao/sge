<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipologia institucional (ex.: Complexo Escolar, Liceu, Instituto Médio Técnico).
 * Conceito independente dos níveis de ensino ministrados.
 */
class TipologiaEscola extends Model
{
    use RegistaActividade;

    public const ROTULO = 'tipologia';

    protected $table = 'tipologias_escola';

    protected $fillable = ['nome', 'sigla', 'descricao', 'ordem', 'activo'];

    protected $casts = ['activo' => 'boolean', 'ordem' => 'integer'];

    public function escolas(): HasMany
    {
        return $this->hasMany(Escola::class, 'tipologia_id');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopeOrdenados(Builder $q): Builder
    {
        return $q->orderBy('ordem')->orderBy('nome');
    }
}
