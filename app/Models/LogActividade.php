<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogActividade extends Model
{
    public const UPDATED_AT = null;

    public const ACCOES = [
        'login' => 'Entrada',
        'logout' => 'Saída',
        'login_falhado' => 'Tentativa falhada',
        'criar' => 'Criação',
        'actualizar' => 'Alteração',
        'eliminar' => 'Eliminação',
        'activar' => 'Activação',
        'desactivar' => 'Desactivação',
        'senha' => 'Senha',
        'permissoes' => 'Permissões',
        'contexto' => 'Acesso a escola',
        'anular' => 'Anulação',
        'fechar' => 'Fecho de período',
        'reabrir' => 'Reabertura de período',
        'recalcular' => 'Recálculo',
        'exportar' => 'Exportação',
        'backup' => 'Cópia de segurança',
        'configuracao' => 'Configuração de avaliação',
    ];

    protected $table = 'logs_actividade';

    protected $fillable = [
        'utilizador_id', 'escola_id', 'accao', 'descricao',
        'modelo_tipo', 'modelo_id', 'dados', 'ip', 'user_agent',
    ];

    protected $casts = ['dados' => 'array', 'created_at' => 'datetime'];

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilizador_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function getAccaoLabelAttribute(): string
    {
        return self::ACCOES[$this->accao] ?? ucfirst($this->accao);
    }

    public function getAccaoCorAttribute(): string
    {
        return match ($this->accao) {
            'criar', 'activar', 'login' => 'success',
            'actualizar', 'permissoes', 'senha' => 'info',
            'eliminar', 'desactivar', 'login_falhado' => 'danger',
            default => 'secondary',
        };
    }
}
