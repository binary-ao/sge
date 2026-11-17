<?php

namespace App\Models\Concerns;

use App\Support\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Regista automaticamente no histórico de actividades as operações de
 * criação, alteração e eliminação do modelo.
 */
trait RegistaActividade
{
    public static function bootRegistaActividade(): void
    {
        static::created(function (Model $m) {
            Auditoria::registar('criar', 'Criou '.static::rotuloAuditoria().': '.$m->nomeAuditoria(), $m,
                ['novo' => static::filtrarAuditoria($m->getAttributes())]);
        });

        static::updated(function (Model $m) {
            $alteracoes = static::filtrarAuditoria($m->getChanges());
            if (empty($alteracoes)) {
                return;
            }
            $anterior = array_intersect_key($m->getOriginal(), $alteracoes);
            Auditoria::registar('actualizar', 'Actualizou '.static::rotuloAuditoria().': '.$m->nomeAuditoria(), $m,
                ['anterior' => static::filtrarAuditoria($anterior), 'novo' => $alteracoes]);
        });

        static::deleted(function (Model $m) {
            Auditoria::registar('eliminar', 'Eliminou '.static::rotuloAuditoria().': '.$m->nomeAuditoria(), $m,
                ['anterior' => static::filtrarAuditoria($m->getAttributes())]);
        });
    }

    /** Remove campos sensíveis ou irrelevantes do histórico. */
    protected static function filtrarAuditoria(array $dados): array
    {
        $ocultos = ['password', 'remember_token', 'updated_at', 'created_at', 'ultimo_acesso_em'];

        return array_diff_key($dados, array_flip($ocultos));
    }

    public static function rotuloAuditoria(): string
    {
        return defined(static::class.'::ROTULO') ? static::ROTULO : class_basename(static::class);
    }

    public function nomeAuditoria(): string
    {
        return (string) ($this->nome ?? $this->codigo ?? '#'.$this->getKey());
    }
}
