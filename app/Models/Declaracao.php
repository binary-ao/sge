<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/** Declaração emitida. O conteúdo é guardado no momento da emissão (reimpressão idêntica). */
class Declaracao extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'declaração';

    protected $table = 'declaracoes';

    protected $fillable = ['aluno_id', 'matricula_id', 'tipo', 'numero', 'ano', 'sequencial', 'finalidade', 'conteudo', 'codigo_verificacao', 'emitida_por'];

    protected $casts = ['conteudo' => 'array'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new RuntimeException('As declarações emitidas ficam no histórico e não podem ser eliminadas.'));
        static::updating(fn () => throw new RuntimeException('Uma declaração emitida não pode ser alterada.'));
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function emitidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitida_por');
    }

    public function getTipoLabelAttribute(): string
    {
        return config('administrativo.declaracoes.tipos.'.$this->tipo, $this->tipo);
    }

    public function nomeAuditoria(): string
    {
        return $this->numero;
    }
}
