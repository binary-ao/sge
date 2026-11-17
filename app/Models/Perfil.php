<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perfil extends Model
{
    use RegistaActividade;

    public const ROTULO = 'perfil';

    public const SUPERUTILIZADOR = 'superutilizador';
    public const DIRECCAO_PROVINCIAL = 'direccao_provincial';
    public const DIRECCAO_MUNICIPAL = 'direccao_municipal';
    public const DIRECTOR_ESCOLA = 'director_escola';
    public const PROFESSOR = 'professor';
    public const SECRETARIA = 'secretaria';

    public const AMBITOS = [
        'global' => 'Global',
        'provincia' => 'Província',
        'municipio' => 'Município',
        'escola' => 'Escola',
    ];

    protected $table = 'perfis';

    protected $fillable = ['codigo', 'nome', 'descricao', 'ambito', 'sistema'];

    protected $casts = ['sistema' => 'boolean'];

    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permissao::class, 'perfil_permissao', 'perfil_id', 'permissao_id');
    }

    public function utilizadores(): HasMany
    {
        return $this->hasMany(User::class, 'perfil_id');
    }

    public function getAmbitoLabelAttribute(): string
    {
        return self::AMBITOS[$this->ambito] ?? $this->ambito;
    }

    public function isSuperutilizador(): bool
    {
        return $this->codigo === self::SUPERUTILIZADOR;
    }
}
