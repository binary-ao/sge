<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'turma';

    protected $table = 'turmas';

    protected $fillable = ['ano_lectivo_id', 'classe_id', 'curso_id', 'turno_id', 'director_turma_id', 'nome', 'sala', 'capacidade', 'activo'];

    protected $casts = ['activo' => 'boolean', 'capacidade' => 'integer'];

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class)->withoutGlobalScope('escola');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function directorTurma(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'director_turma_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /** Matrículas que ocupam lugar na turma. */
    public function matriculasActivas(): HasMany
    {
        return $this->hasMany(Matricula::class)->where('estado', 'activa');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(AtribuicaoProfessor::class);
    }

    public function planoCurricular(): ?PlanoCurricular
    {
        return PlanoCurricular::resolver($this->escola_id, $this->ano_lectivo_id, $this->classe_id, $this->curso_id);
    }

    public function scopeDoProfessor(Builder $q, Professor $professor): Builder
    {
        return $q->where(fn ($w) => $w
            ->whereHas('atribuicoes', fn ($a) => $a->where('professor_id', $professor->id))
            ->orWhere('director_turma_id', $professor->id));
    }

    /** Designação completa, ex.: "10.ª Classe A · CFB". */
    public function getDesignacaoAttribute(): string
    {
        return trim(($this->classe?->nome ?? '').' '.$this->nome.($this->curso ? ' · '.$this->curso->codigo : ''));
    }

    public function nomeAuditoria(): string
    {
        return $this->designacao;
    }
}
