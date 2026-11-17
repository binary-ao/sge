<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trimestre extends Model
{
    use \App\Models\Concerns\RegistaActividade;

    public const ROTULO = 'período de avaliação';

    protected $table = 'trimestres';

    protected $fillable = ['ano_lectivo_id', 'numero', 'designacao', 'data_inicio', 'data_fim'];

    protected $casts = ['data_inicio' => 'date', 'data_fim' => 'date', 'numero' => 'integer'];

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public const SITUACOES = [
        'sem_datas' => ['Sem datas', 'secondary'],
        'por_iniciar' => ['Por iniciar', 'info'],
        'a_decorrer' => ['A decorrer', 'success'],
        'concluido' => ['Concluído', 'dark'],
    ];

    /** Situação do período face à data de hoje (sem_datas, por_iniciar, a_decorrer, concluido). */
    public function getSituacaoAttribute(): string
    {
        if (! $this->data_inicio || ! $this->data_fim) {
            return 'sem_datas';
        }
        $hoje = now()->startOfDay();

        return match (true) {
            $hoje->lt($this->data_inicio) => 'por_iniciar',
            $hoje->gt($this->data_fim) => 'concluido',
            default => 'a_decorrer',
        };
    }

    /** Filtra pela situação calculada a partir das datas. */
    public function scopeNaSituacao(\Illuminate\Database\Eloquent\Builder $q, string $situacao): \Illuminate\Database\Eloquent\Builder
    {
        $hoje = now()->toDateString();

        return match ($situacao) {
            'sem_datas' => $q->where(fn ($w) => $w->whereNull('trimestres.data_inicio')->orWhereNull('trimestres.data_fim')),
            'por_iniciar' => $q->whereNotNull('trimestres.data_fim')->where('trimestres.data_inicio', '>', $hoje),
            'a_decorrer' => $q->where('trimestres.data_inicio', '<=', $hoje)->where('trimestres.data_fim', '>=', $hoje),
            'concluido' => $q->whereNotNull('trimestres.data_inicio')->where('trimestres.data_fim', '<', $hoje),
            default => $q,
        };
    }

    public function nomeAuditoria(): string
    {
        return $this->designacao.' '.($this->anoLectivo?->designacao ?? '');
    }
}
