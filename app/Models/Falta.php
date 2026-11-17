<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Falta extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'falta';

    public const JUSTIFICACOES = [
        'nao_justificada' => 'Não justificada',
        'pendente' => 'Justificativo pendente',
        'aprovado' => 'Justificada',
        'rejeitado' => 'Justificativo rejeitado',
    ];

    protected $table = 'faltas';

    protected $fillable = ['matricula_id', 'turma_id', 'disciplina_id', 'professor_id', 'data', 'tempos', 'motivo', 'justificacao', 'registado_por'];

    protected $casts = ['data' => 'date', 'tempos' => 'integer'];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class)->withoutGlobalScope('escola');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class);
    }

    public function justificativo(): HasOne
    {
        return $this->hasOne(JustificativoFalta::class)->latestOfMany();
    }

    public function getJustificacaoLabelAttribute(): string
    {
        return self::JUSTIFICACOES[$this->justificacao] ?? $this->justificacao;
    }

    public function getJustificacaoCorAttribute(): string
    {
        return ['nao_justificada' => 'secondary', 'pendente' => 'warning', 'aprovado' => 'success', 'rejeitado' => 'danger'][$this->justificacao] ?? 'secondary';
    }

    public function nomeAuditoria(): string
    {
        return "matrícula #{$this->matricula_id} · {$this->data?->format('d/m/Y')}";
    }
}
