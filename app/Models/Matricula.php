<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'matrícula';

    public const TIPOS = ['nova' => 'Nova matrícula', 'confirmacao' => 'Confirmação', 'transferencia' => 'Transferência (entrada)'];

    public const ESTADOS = [
        'activa' => 'Activa',
        'transferida' => 'Transferida',
        'desistente' => 'Desistente',
        'concluida' => 'Concluída',
        'anulada' => 'Anulada',
    ];

    protected $table = 'matriculas';

    protected $fillable = [
        'aluno_id', 'ano_lectivo_id', 'classe_id', 'curso_id', 'turma_id', 'turno_id',
        'numero', 'tipo', 'data_matricula', 'estado', 'observacoes', 'registado_por',
    ];

    protected $casts = ['data_matricula' => 'date'];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

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

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function faltas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Falta::class);
    }

    public function registadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registado_por');
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getEstadoCorAttribute(): string
    {
        return match ($this->estado) {
            'activa' => 'success',
            'concluida' => 'primary',
            'transferida' => 'info',
            'desistente', 'anulada' => 'danger',
            default => 'secondary',
        };
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function nomeAuditoria(): string
    {
        return $this->numero;
    }
}
