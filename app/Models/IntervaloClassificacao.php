<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntervaloClassificacao extends Model
{
    public $timestamps = false;

    protected $table = 'intervalos_classificacao';

    protected $fillable = ['escala_id', 'minimo', 'maximo', 'rotulo', 'sigla', 'valor_referencia', 'ordem'];

    protected $casts = ['minimo' => 'float', 'maximo' => 'float', 'valor_referencia' => 'float'];

    public function escala(): BelongsTo
    {
        return $this->belongsTo(EscalaClassificacao::class, 'escala_id');
    }
}
