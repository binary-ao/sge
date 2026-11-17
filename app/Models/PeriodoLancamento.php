<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoLancamento extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'período de lançamento';

    public const PERIODOS = [1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre', 4 => 'Exames'];

    protected $table = 'periodos_lancamento';

    protected $fillable = ['ano_lectivo_id', 'periodo', 'estado', 'alterado_por', 'alterado_em', 'motivo'];

    protected $casts = ['alterado_em' => 'datetime', 'periodo' => 'integer'];

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function alteradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alterado_por');
    }

    public static function estaFechado(int $escolaId, int $anoLectivoId, int $periodo): bool
    {
        return static::withoutGlobalScope('escola')
            ->where(['escola_id' => $escolaId, 'ano_lectivo_id' => $anoLectivoId, 'periodo' => $periodo, 'estado' => 'fechado'])
            ->exists();
    }

    public function nomeAuditoria(): string
    {
        return self::PERIODOS[$this->periodo] ?? (string) $this->periodo;
    }
}
