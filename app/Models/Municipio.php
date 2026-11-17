<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Support\ContextoEscola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipio extends Model
{
    use RegistaActividade;

    public const ROTULO = 'município';

    protected $table = 'municipios';

    protected $fillable = ['provincia_id', 'nome', 'codigo', 'sede', 'latitude', 'longitude', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function escolas(): HasMany
    {
        return $this->hasMany(Escola::class);
    }

    /** Alunos das escolas do município (para agregações; sem o filtro da escola activa). */
    public function alunos(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Aluno::class, Escola::class)->withoutGlobalScope('escola');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /** Municípios que o utilizador autenticado pode consultar. */
    public function scopeVisiveis(Builder $q): Builder
    {
        $u = auth()->user();
        $ambito = app(ContextoEscola::class)->ambito();

        return match ($ambito) {
            'global', 'provincia' => $q,
            'municipio' => $q->whereKey($u->municipio_id),
            'escola' => $q->whereIn('id', Escola::whereIn('id', app(ContextoEscola::class)->idsEscolasVisiveis())->pluck('municipio_id')),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
