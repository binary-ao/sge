<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtribuicaoProfessor extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'atribuição de professor';

    protected $table = 'atribuicoes_professor';

    protected $fillable = ['professor_id', 'turma_id', 'disciplina_id'];

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class)->withoutGlobalScope('escola');
    }

    public function nomeAuditoria(): string
    {
        return "professor #{$this->professor_id} · turma #{$this->turma_id} · disciplina #{$this->disciplina_id}";
    }
}
