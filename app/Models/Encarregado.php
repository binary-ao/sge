<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encarregado extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'encarregado de educação';

    protected $table = 'encarregados';

    protected $fillable = ['nome', 'parentesco', 'numero_identificacao', 'telefone', 'telefone_alternativo', 'email', 'profissao', 'endereco'];

    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }
}
