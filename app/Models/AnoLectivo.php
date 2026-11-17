<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnoLectivo extends Model
{
    use RegistaActividade;

    public const ROTULO = 'ano lectivo';

    public const ESTADOS = ['planeado' => 'Planeado', 'em_curso' => 'Em curso', 'encerrado' => 'Encerrado'];

    protected $table = 'anos_lectivos';

    protected $fillable = ['designacao', 'data_inicio', 'data_fim', 'estado', 'actual'];

    protected $casts = ['data_inicio' => 'date', 'data_fim' => 'date', 'actual' => 'boolean'];

    public function trimestres(): HasMany
    {
        return $this->hasMany(Trimestre::class)->orderBy('numero');
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public static function actual(): ?self
    {
        return static::where('actual', true)->first() ?? static::orderByDesc('data_inicio')->first();
    }

    public function scopeRecentes(Builder $q): Builder
    {
        return $q->orderByDesc('data_inicio');
    }

    public function nomeAuditoria(): string
    {
        return $this->designacao;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
