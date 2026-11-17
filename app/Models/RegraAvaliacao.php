<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Regra parametrizada de aprovação, transição, frequência ou acesso a exames.
 * O catálogo de tipos e parâmetros está em config/avaliacao.php.
 */
class RegraAvaliacao extends Model
{
    public $timestamps = false;

    protected $table = 'regras_avaliacao';

    protected $fillable = ['configuracao_avaliacao_id', 'tipo', 'parametros', 'activo', 'ordem'];

    protected $casts = ['parametros' => 'array', 'activo' => 'boolean'];

    public function getDefinicaoAttribute(): array
    {
        return config('avaliacao.regras.'.$this->tipo, ['nome' => $this->tipo, 'parametros' => []]);
    }
}
