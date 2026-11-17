<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Nível de ensino ministrado (ex.: Ensino Primário, I Ciclo do Ensino Secundário).
 * Uma escola pode ministrar vários níveis.
 */
class NivelEnsino extends Model
{
    use RegistaActividade;

    public const ROTULO = 'nível de ensino';

    protected $table = 'niveis_ensino';

    protected $fillable = ['nome', 'codigo', 'descricao', 'exige_curso', 'ordem', 'activo'];

    protected $casts = ['activo' => 'boolean', 'exige_curso' => 'boolean', 'ordem' => 'integer'];

    public function escolas(): BelongsToMany
    {
        return $this->belongsToMany(Escola::class, 'escola_nivel_ensino', 'nivel_ensino_id', 'escola_id');
    }

    public function ciclos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ciclo::class)->orderBy('ordem');
    }

    public function classes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Classe::class)->orderBy('ordem');
    }

    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'disciplina_nivel_ensino', 'nivel_ensino_id', 'disciplina_id');
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
