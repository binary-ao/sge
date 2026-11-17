<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciclo extends Model
{
    use RegistaActividade;

    public const ROTULO = 'ciclo';

    protected $table = 'ciclos';

    protected $fillable = ['nivel_ensino_id', 'nome', 'ordem', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function nivelEnsino(): BelongsTo
    {
        return $this->belongsTo(NivelEnsino::class);
    }

    public function scopeOrdenados(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->orderBy(NivelEnsino::select('ordem')->whereColumn('niveis_ensino.id', 'ciclos.nivel_ensino_id'))
            ->orderBy('ciclos.ordem')->orderBy('ciclos.nome');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
