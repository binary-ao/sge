<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configuração de avaliação aplicável a um ano lectivo, nível, ciclo, classes
 * (e, opcionalmente, a uma disciplina). Versionada: cada alteração incrementa
 * "versao" e grava uma cópia completa em historico_configuracao.
 */
class ConfiguracaoAvaliacao extends Model
{
    use RegistaActividade;

    public const ROTULO = 'configuração de avaliação';

    public const ESTADOS = ['rascunho' => 'Rascunho', 'activa' => 'Activa', 'inactiva' => 'Inactiva'];

    public const TIPOS = ['quantitativa' => 'Quantitativa', 'qualitativa' => 'Qualitativa', 'mista' => 'Qualitativa e quantitativa'];

    public const ARREDONDAMENTOS = ['unidade' => 'À unidade mais próxima', 'decimas' => 'Às décimas', 'nenhum' => 'Sem arredondamento (2 casas)'];

    protected $table = 'configuracoes_avaliacao';

    protected $fillable = [
        'sistema_avaliacao_id', 'ano_lectivo_id', 'nivel_ensino_id', 'ciclo_id', 'disciplina_id', 'escala_id',
        'nome', 'descricao', 'tipo_classificacao', 'nota_minima_aprovacao', 'arredondamento',
        'variavel_trimestral', 'variavel_final', 'estado', 'versao', 'criado_por', 'actualizado_por',
    ];

    protected $casts = ['nota_minima_aprovacao' => 'float', 'versao' => 'integer'];

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(SistemaAvaliacao::class, 'sistema_avaliacao_id');
    }

    public function anoLectivo(): BelongsTo
    {
        return $this->belongsTo(AnoLectivo::class);
    }

    public function nivelEnsino(): BelongsTo
    {
        return $this->belongsTo(NivelEnsino::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class)->withoutGlobalScope('escola');
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(EscalaClassificacao::class, 'escala_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'configuracao_avaliacao_classe');
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(ComponenteAvaliacao::class)->orderBy('ordem');
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(FormulaAvaliacao::class)->orderBy('ordem');
    }

    public function regras(): HasMany
    {
        return $this->hasMany(RegraAvaliacao::class)->orderBy('ordem');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(HistoricoConfiguracao::class, 'entidade_id')->where('entidade', 'configuracao')->orderByDesc('versao');
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('estado', 'activa');
    }

    /** Cópia completa da configuração (guardada em cada versão). */
    public function snapshot(): array
    {
        $this->loadMissing('componentes', 'formulas', 'regras', 'classes', 'escala.intervalos');

        return [
            'campos' => $this->only('sistema_avaliacao_id', 'ano_lectivo_id', 'nivel_ensino_id', 'ciclo_id', 'disciplina_id', 'escala_id',
                'nome', 'descricao', 'tipo_classificacao', 'nota_minima_aprovacao', 'arredondamento',
                'variavel_trimestral', 'variavel_final', 'estado'),
            'classes' => $this->classes->pluck('id')->all(),
            'componentes' => $this->componentes->map->only('codigo', 'nome', 'tipo', 'momento', 'trimestres', 'itens_previstos', 'nota_minima', 'nota_maxima', 'ordem')->values()->all(),
            'formulas' => $this->formulas->map->only('codigo', 'nome', 'expressao', 'nivel', 'trimestres', 'arredondar', 'ordem', 'modelo_formula_id')->values()->all(),
            'regras' => $this->regras->map->only('tipo', 'parametros', 'activo', 'ordem')->values()->all(),
            'escala' => $this->escala?->snapshot(),
        ];
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function eQualitativa(): bool
    {
        return $this->tipo_classificacao === 'qualitativa';
    }
}
