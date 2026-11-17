<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicoPagavel extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'serviço';

    protected $table = 'servicos_pagaveis';

    protected $fillable = ['classe_id', 'tipo', 'nome', 'valor', 'activo'];

    protected $casts = ['valor' => 'float', 'activo' => 'boolean'];

    public function pagamentos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Pagamento::class, 'servico_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** Serviços aplicáveis a uma classe: os específicos da classe prevalecem sobre os gerais do mesmo tipo e nome. */
    public function scopeParaClasse(Builder $q, ?int $classeId): Builder
    {
        return $q->where('activo', true)->where(fn ($w) => $w->whereNull('classe_id')->when($classeId, fn ($x) => $x->orWhere('classe_id', $classeId)));
    }

    public function getTipoLabelAttribute(): string
    {
        return config('administrativo.pagamentos.tipos_servico.'.$this->tipo, $this->tipo);
    }
}
