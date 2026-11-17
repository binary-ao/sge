<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoConfiguracao extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historico_configuracao';

    protected $fillable = ['entidade', 'entidade_id', 'versao', 'accao', 'motivo', 'snapshot', 'utilizador_id'];

    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilizador_id');
    }

    public static function registar(Model $entidade, string $tipo, string $accao, ?string $motivo): self
    {
        return static::create([
            'entidade' => $tipo,
            'entidade_id' => $entidade->getKey(),
            'versao' => $entidade->versao,
            'accao' => $accao,
            'motivo' => $motivo,
            'snapshot' => $entidade->snapshot(),
            'utilizador_id' => auth()->id(),
        ]);
    }
}
