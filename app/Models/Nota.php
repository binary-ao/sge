<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nota extends Model
{
    use PertenceAEscola;

    protected $table = 'notas';

    protected $fillable = ['avaliacao_id', 'matricula_id', 'valor', 'lancado_por'];

    protected $casts = ['valor' => 'float'];

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }
}
