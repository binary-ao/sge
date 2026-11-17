<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;

/** Modelo regulamentar de fórmula (Anexo III do Decreto Executivo n.º 424/25). */
class ModeloFormula extends Model
{
    use RegistaActividade;

    public const ROTULO = 'modelo de fórmula';

    protected $table = 'modelos_formula';

    protected $fillable = ['referencia', 'codigo', 'nome', 'expressao', 'nivel', 'descricao', 'aplicacao', 'versao', 'activo'];

    protected $casts = ['activo' => 'boolean', 'versao' => 'integer'];

    public function snapshot(): array
    {
        return $this->only('referencia', 'codigo', 'nome', 'expressao', 'nivel', 'descricao', 'aplicacao', 'activo');
    }
}
