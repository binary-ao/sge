<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resultado de uma disciplina num trimestre (periodo 1–3) ou final (periodo 0). */
class ResultadoAvaliacao extends Model
{
    use PertenceAEscola;

    public $timestamps = false;

    protected $table = 'resultados_avaliacao';

    protected $fillable = ['matricula_id', 'turma_id', 'disciplina_id', 'periodo', 'valores', 'classificacao', 'classificacao_qualitativa',
        'situacao', 'configuracao_avaliacao_id', 'configuracao_versao', 'calculado_em', 'calculado_por'];

    protected $casts = ['valores' => 'array', 'classificacao' => 'float', 'calculado_em' => 'datetime', 'periodo' => 'integer'];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class)->withoutGlobalScope('escola');
    }

    public function configuracao(): BelongsTo
    {
        return $this->belongsTo(ConfiguracaoAvaliacao::class, 'configuracao_avaliacao_id');
    }
}
