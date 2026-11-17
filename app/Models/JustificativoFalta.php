<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustificativoFalta extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'justificativo de falta';

    public const ESTADOS = ['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado'];

    protected $table = 'justificativos_falta';

    protected $fillable = ['falta_id', 'motivo', 'documento', 'documento_nome', 'estado', 'submetido_por', 'analisado_por', 'analisado_em', 'observacao'];

    protected $casts = ['analisado_em' => 'datetime'];

    public function falta(): BelongsTo
    {
        return $this->belongsTo(Falta::class);
    }

    public function submetidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submetido_por');
    }

    public function analisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analisado_por');
    }

    public function nomeAuditoria(): string
    {
        return "falta #{$this->falta_id}";
    }
}
