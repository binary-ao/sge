<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SistemaAvaliacao extends Model
{
    use RegistaActividade;

    public const ROTULO = 'sistema de avaliação';

    protected $table = 'sistemas_avaliacao';

    protected $fillable = ['nome', 'descricao', 'base_legal', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function configuracoes(): HasMany
    {
        return $this->hasMany(ConfiguracaoAvaliacao::class);
    }
}
