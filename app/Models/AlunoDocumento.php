<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEscola;
use App\Models\Concerns\RegistaActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlunoDocumento extends Model
{
    use PertenceAEscola, RegistaActividade;

    public const ROTULO = 'documento de aluno';

    public const TIPOS = [
        'identificacao' => 'Documento de identificação',
        'certificado' => 'Certificado de habilitações',
        'declaracao' => 'Declaração',
        'atestado_medico' => 'Atestado médico',
        'fotografia' => 'Fotografia',
        'outro' => 'Outro',
    ];

    protected $table = 'aluno_documentos';

    protected $fillable = ['aluno_id', 'tipo', 'descricao', 'caminho', 'nome_original', 'mime', 'tamanho', 'carregado_por'];

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function nomeAuditoria(): string
    {
        return $this->nome_original;
    }
}
