<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Aluno extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'aluno';

    public const ESTADOS = [
        'activo' => 'Activo',
        'transferido' => 'Transferido',
        'desistente' => 'Desistente',
        'concluido' => 'Concluído',
    ];

    public const TIPOS_DOCUMENTO = [
        'bi' => 'Bilhete de Identidade',
        'cedula' => 'Cédula pessoal',
        'passaporte' => 'Passaporte',
        'outro' => 'Outro',
    ];

    protected $table = 'alunos';

    protected $fillable = [
        'encarregado_id', 'nome', 'genero', 'data_nascimento', 'naturalidade', 'nacionalidade',
        'tipo_documento', 'numero_identificacao', 'endereco', 'telefone', 'email',
        'necessidades_especiais', 'observacoes',
    ];

    protected $casts = ['data_nascimento' => 'date', 'data_estado' => 'date'];

    public function encarregado(): BelongsTo
    {
        return $this->belongsTo(Encarregado::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function matriculaActual(): HasOne
    {
        return $this->hasOne(Matricula::class)->ofMany(['ano_lectivo_id' => 'max']);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(AlunoDocumento::class)->latest();
    }

    public function scopePesquisa(Builder $q, ?string $termo): Builder
    {
        return $q->when($termo, fn ($q) => $q->where(fn ($w) => $w
            ->where('alunos.nome', 'like', "%{$termo}%")
            ->orWhere('alunos.numero_processo', 'like', "%{$termo}%")
            ->orWhere('alunos.numero_identificacao', 'like', "%{$termo}%")));
    }

    public function getIdadeAttribute(): ?int
    {
        return $this->data_nascimento?->age;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getEstadoCorAttribute(): string
    {
        return match ($this->estado) {
            'activo' => 'success',
            'transferido' => 'info',
            'desistente' => 'danger',
            'concluido' => 'primary',
            default => 'secondary',
        };
    }
}
