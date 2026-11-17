<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Professor extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'professor';

    public const HABILITACOES = [
        'ensino_medio' => 'Ensino Médio',
        'bacharelato' => 'Bacharelato',
        'licenciatura' => 'Licenciatura',
        'mestrado' => 'Mestrado',
        'doutoramento' => 'Doutoramento',
        'outra' => 'Outra',
    ];

    public const SITUACOES = [
        'efectivo' => 'Efectivo',
        'contratado' => 'Contratado',
        'colaborador' => 'Colaborador',
        'estagiario' => 'Estagiário',
    ];

    protected $table = 'professores';

    protected $fillable = [
        'utilizador_id', 'nome', 'genero', 'data_nascimento', 'numero_identificacao', 'telefone', 'email',
        'habilitacoes', 'especialidade', 'situacao_profissional', 'data_admissao', 'activo',
    ];

    protected $casts = ['data_nascimento' => 'date', 'data_admissao' => 'date', 'activo' => 'boolean'];

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilizador_id');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(AtribuicaoProfessor::class);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function getHabilitacoesLabelAttribute(): string
    {
        return self::HABILITACOES[$this->habilitacoes] ?? $this->habilitacoes;
    }

    public function getSituacaoLabelAttribute(): string
    {
        return self::SITUACOES[$this->situacao_profissional] ?? $this->situacao_profissional;
    }
}
