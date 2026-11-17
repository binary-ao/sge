<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Recibo emitido. Numeração sequencial por escola e ano. NUNCA é eliminado:
 * apenas anulado, com registo do utilizador, data e motivo.
 */
class Recibo extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'recibo';

    protected $table = 'recibos';

    protected $fillable = ['aluno_id', 'numero', 'ano', 'sequencial', 'data', 'total', 'forma_pagamento', 'referencia', 'estado',
        'emitido_por', 'anulado_por', 'anulado_em', 'motivo_anulacao'];

    protected $casts = ['data' => 'date', 'total' => 'float', 'anulado_em' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new RuntimeException('Os recibos emitidos não podem ser eliminados; apenas anulados.');
        });
        static::updating(function (Recibo $r) {
            // Depois de emitido, só o estado de anulação pode mudar.
            $permitidos = ['estado', 'anulado_por', 'anulado_em', 'motivo_anulacao', 'updated_at'];
            if (array_diff(array_keys($r->getDirty()), $permitidos)) {
                throw new RuntimeException('Um recibo emitido não pode ser alterado.');
            }
        });
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function getFormaLabelAttribute(): string
    {
        return config('administrativo.pagamentos.formas.'.$this->forma_pagamento, $this->forma_pagamento);
    }

    public function nomeAuditoria(): string
    {
        return $this->numero;
    }
}
