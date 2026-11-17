<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormulaAvaliacao extends Model
{
    public $timestamps = false;

    protected $table = 'formulas_avaliacao';

    protected $fillable = ['configuracao_avaliacao_id', 'modelo_formula_id', 'codigo', 'nome', 'expressao', 'nivel', 'trimestres', 'arredondar', 'ordem'];

    protected $casts = ['arredondar' => 'boolean'];

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloFormula::class, 'modelo_formula_id');
    }
}
