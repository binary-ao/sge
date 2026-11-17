<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscolaOuGlobal;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curso extends Model
{
    use PertenceAEscolaOuGlobal, RegistaActividade;

    public const ROTULO = 'curso';

    protected $table = 'cursos';

    protected $fillable = ['escola_id', 'nivel_ensino_id', 'nome', 'codigo', 'descricao', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function nivelEnsino(): BelongsTo
    {
        return $this->belongsTo(NivelEnsino::class);
    }
}
