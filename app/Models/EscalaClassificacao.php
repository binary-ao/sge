<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaClassificacao extends Model
{
    use RegistaActividade;

    public const ROTULO = 'escala de classificação';

    protected $table = 'escalas_classificacao';

    protected $fillable = ['nome', 'descricao', 'nota_minima', 'nota_maxima', 'versao', 'activo'];

    protected $casts = ['nota_minima' => 'float', 'nota_maxima' => 'float', 'activo' => 'boolean', 'versao' => 'integer'];

    public function intervalos(): HasMany
    {
        return $this->hasMany(IntervaloClassificacao::class, 'escala_id')->orderByDesc('minimo');
    }

    /** Intervalo correspondente a uma classificação (já arredondada). */
    public function intervaloPara(?float $valor): ?IntervaloClassificacao
    {
        if ($valor === null) {
            return null;
        }

        return $this->intervalos->first(fn ($i) => $valor >= $i->minimo - 1e-9 && $valor <= $i->maximo + 1e-9)
            // valores intermédios não arredondados (ex.: 8,5 numa escala de inteiros): intervalo imediatamente inferior
            ?? $this->intervalos->first(fn ($i) => $valor >= $i->minimo);
    }

    public function snapshot(): array
    {
        return $this->only('nome', 'descricao', 'nota_minima', 'nota_maxima', 'activo')
            + ['intervalos' => $this->intervalos->map->only('minimo', 'maximo', 'rotulo', 'sigla', 'valor_referencia')->values()->all()];
    }
}
