<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    use RegistaActividade;

    public const ROTULO = 'classe';

    /** Regime usado pelo motor de avaliação (Fase 4). */
    public const REGIMES = [
        'transicao' => 'Classe de transição',
        'exame_nacional' => 'Classe de Exame Nacional',
        'terminal' => 'Classe terminal',
        'sem_retencao' => 'Sem retenção (progressão automática)',
    ];

    protected $table = 'classes';

    protected $fillable = ['nivel_ensino_id', 'ciclo_id', 'nome', 'numero', 'regime', 'ordem', 'activo'];

    protected $casts = ['activo' => 'boolean', 'numero' => 'integer'];

    public function nivelEnsino(): BelongsTo
    {
        return $this->belongsTo(NivelEnsino::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function scopeOrdenadas(Builder $q): Builder
    {
        // Subconsulta (sem join) para não criar ambiguidade de colunas noutros filtros.
        return $q->orderBy(NivelEnsino::select('ordem')->whereColumn('niveis_ensino.id', 'classes.nivel_ensino_id'))
            ->orderBy('classes.ordem')->orderBy('classes.numero');
    }

    /** Classes dos níveis ministrados por uma escola. */
    public function scopeDaEscola(Builder $q, Escola $escola): Builder
    {
        return $q->whereIn('classes.nivel_ensino_id', $escola->niveisEnsino()->pluck('niveis_ensino.id'));
    }

    /** Nome com o nível quando o nome se repete entre níveis (ex.: 10.ª Classe no ESG e no ETP). */
    public function getNomeCompletoAttribute(): string
    {
        return $this->nome.' — '.($this->nivelEnsino?->nome ?? '');
    }

    public function getRegimeLabelAttribute(): string
    {
        return self::REGIMES[$this->regime] ?? $this->regime;
    }
}
