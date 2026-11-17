<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscolaOuGlobal;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;

class Disciplina extends Model
{
    use PertenceAEscolaOuGlobal, RegistaActividade;

    public const ROTULO = 'disciplina';

    public const COMPONENTES = [
        'geral' => 'Formação geral',
        'especifica' => 'Formação específica',
        'tecnica' => 'Formação técnica / profissional',
        'complementar' => 'Complementar',
    ];

    protected $table = 'disciplinas';

    protected $fillable = ['escola_id', 'nome', 'sigla', 'nuclear', 'componente', 'activo'];

    protected $casts = ['activo' => 'boolean', 'nuclear' => 'boolean'];

    /** Níveis de ensino a que a disciplina se aplica. */
    public function niveisEnsino(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(NivelEnsino::class, 'disciplina_nivel_ensino', 'disciplina_id', 'nivel_ensino_id')
            ->orderBy('ordem');
    }

    /** Planos curriculares (classes) em que a disciplina está incluída. */
    public function planos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(PlanoCurricular::class, 'plano_curricular_disciplina')
            ->withPivot('carga_horaria', 'especifica', 'obrigatoria', 'ordem');
    }

    public function getComponenteLabelAttribute(): string
    {
        return self::COMPONENTES[$this->componente] ?? $this->componente;
    }
}
