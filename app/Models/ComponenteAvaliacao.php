<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComponenteAvaliacao extends Model
{
    public $timestamps = false;

    protected $table = 'componentes_avaliacao';

    protected $fillable = ['configuracao_avaliacao_id', 'codigo', 'nome', 'tipo', 'momento', 'trimestres', 'itens_previstos', 'nota_minima', 'nota_maxima', 'ordem'];

    protected $casts = ['nota_minima' => 'float', 'nota_maxima' => 'float', 'itens_previstos' => 'integer'];

    /** O componente é lançado neste período? (1–3 trimestres, 4 = exames) */
    public function aplicaAoPeriodo(int $periodo): bool
    {
        if ($this->momento === 'exame') {
            return $periodo === 4;
        }

        return $periodo >= 1 && $periodo <= 3
            && ($this->trimestres === null || $this->trimestres === '' || in_array((string) $periodo, explode(',', $this->trimestres), true));
    }
}
