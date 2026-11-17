<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Instrumento de avaliação (coluna da grelha de lançamento). */
class Avaliacao extends Model
{
    use PertenceAEscola;

    protected $table = 'avaliacoes';

    protected $fillable = ['turma_id', 'disciplina_id', 'periodo', 'componente', 'indice', 'descricao', 'data'];

    protected $casts = ['data' => 'date', 'periodo' => 'integer', 'indice' => 'integer'];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function getRotuloAttribute(): string
    {
        return $this->componente.($this->indice > 1 || $this->componente === 'AC' ? $this->indice : '');
    }
}
