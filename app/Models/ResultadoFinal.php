<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Situação final do aluno no ano lectivo, com a justificação do resultado. */
class ResultadoFinal extends Model
{
    use PertenceAEscola;

    public $timestamps = false;

    protected $table = 'resultados_finais';

    protected $fillable = ['matricula_id', 'ano_lectivo_id', 'situacao', 'rotulo', 'justificacao', 'media', 'disciplinas_negativas',
        'configuracao_avaliacao_id', 'configuracao_versao', 'calculado_em'];

    protected $casts = ['justificacao' => 'array', 'media' => 'float', 'calculado_em' => 'datetime'];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function getCorAttribute(): string
    {
        return config('avaliacao.situacoes.'.$this->situacao.'.cor', 'secondary');
    }
}
