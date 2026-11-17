<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscolaOuGlobal;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Conjunto de disciplinas de uma classe (e curso, quando aplicável).
 * Pode ser de referência provincial (escola_id null) ou próprio da escola.
 */
class PlanoCurricular extends Model
{
    use PertenceAEscolaOuGlobal, RegistaActividade;

    public const ROTULO = 'plano curricular';

    protected $table = 'planos_curriculares';

    protected $fillable = ['escola_id', 'ano_lectivo_id', 'classe_id', 'curso_id', 'nome', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function disciplinas(): BelongsToMany
    {
        return $this->belongsToMany(Disciplina::class, 'plano_curricular_disciplina')
            ->withPivot('carga_horaria', 'especifica', 'obrigatoria', 'ordem')
            ->orderByPivot('ordem');
    }

    /**
     * Plano aplicável a uma turma/matrícula. Prioridade:
     * escola + ano lectivo  >  escola (vigente)  >  referência provincial + ano  >  referência provincial (vigente).
     */
    public static function resolver(int $escolaId, int $anoLectivoId, int $classeId, ?int $cursoId): ?self
    {
        return static::withoutGlobalScope('escola')
            ->where('activo', true)
            ->where('classe_id', $classeId)
            ->where(fn ($q) => $cursoId ? $q->where('curso_id', $cursoId) : $q->whereNull('curso_id'))
            ->where(fn ($q) => $q->whereNull('escola_id')->orWhere('escola_id', $escolaId))
            ->where(fn ($q) => $q->whereNull('ano_lectivo_id')->orWhere('ano_lectivo_id', $anoLectivoId))
            ->orderByRaw('escola_id IS NULL')        // da escola primeiro
            ->orderByRaw('ano_lectivo_id IS NULL')   // do ano específico primeiro
            ->first();
    }
}
