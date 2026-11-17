<?php

namespace App\Models;

use App\Models\Concerns\RegistaActividade;
use App\Support\ContextoEscola;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Escola extends Model
{
    use RegistaActividade;

    public const ROTULO = 'escola';

    public const NATUREZAS = [
        'publica' => 'Pública',
        'publico_privada' => 'Público-Privada',
        'privada' => 'Privada',
    ];

    public const ESTADOS = [
        'em_funcionamento' => 'Em funcionamento',
        'temporariamente_encerrada' => 'Temporariamente encerrada',
        'encerrada' => 'Encerrada',
        'em_construcao' => 'Em construção',
    ];

    protected $table = 'escolas';

    protected $fillable = [
        'municipio_id', 'tipologia_id', 'nome', 'codigo', 'natureza',
        'comuna', 'bairro', 'endereco', 'latitude', 'longitude', 'coordenadas_demonstrativas',
        'telefone', 'telefone_alternativo', 'email',
        'director_nome', 'director_telefone',
        'estado_funcionamento', 'ano_inicio_actividade', 'numero_salas', 'dados_adicionais',
        // Alterado apenas pela acção activar/desactivar (não consta das regras do formulário).
        'activo',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'coordenadas_demonstrativas' => 'boolean',
        'activo' => 'boolean',
        'ano_inicio_actividade' => 'integer',
        'numero_salas' => 'integer',
        'dados_adicionais' => 'array',
    ];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function tipologia(): BelongsTo
    {
        return $this->belongsTo(TipologiaEscola::class, 'tipologia_id');
    }

    public function niveisEnsino(): BelongsToMany
    {
        return $this->belongsToMany(NivelEnsino::class, 'escola_nivel_ensino', 'escola_id', 'nivel_ensino_id')
            ->orderBy('ordem');
    }

    public function utilizadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'utilizador_escola', 'escola_id', 'utilizador_id');
    }

    /*
     * Relações operacionais. Nas contagens agregadas (várias escolas) usar
     * withoutGlobalScope('escola'), pois a lista de escolas já está restrita ao âmbito.
     */
    public function alunos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    public function professores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Professor::class);
    }

    public function turmas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function matriculas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Acrescenta contagens de alunos activos, professores activos e turmas do ano lectivo indicado.
     */
    public function scopeComIndicadores(Builder $q, ?int $anoLectivoId): Builder
    {
        return $q->withCount([
            'alunos as total_alunos' => fn ($a) => $a->withoutGlobalScope('escola')->where('estado', 'activo'),
            'professores as total_professores' => fn ($p) => $p->withoutGlobalScope('escola')->where('activo', true),
            'turmas as total_turmas' => fn ($t) => $t->withoutGlobalScope('escola')->where('ano_lectivo_id', $anoLectivoId),
        ]);
    }

    /** Registo activo (uma escola desactivada não entra nos indicadores nem pode ser escolhida como escola activa). */
    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('escolas.activo', true);
    }

    /** Escolas que o utilizador autenticado pode consultar (isolamento no backend). */
    public function scopeVisiveis(Builder $q): Builder
    {
        $ids = app(ContextoEscola::class)->idsEscolasVisiveis();

        return $ids === null ? $q : $q->whereIn('escolas.id', $ids);
    }

    public function getNaturezaLabelAttribute(): string
    {
        return self::NATUREZAS[$this->natureza] ?? $this->natureza;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado_funcionamento] ?? $this->estado_funcionamento;
    }

    public function getEstadoCorAttribute(): string
    {
        return match ($this->estado_funcionamento) {
            'em_funcionamento' => 'success',
            'temporariamente_encerrada' => 'warning',
            'encerrada' => 'danger',
            default => 'secondary',
        };
    }
}
