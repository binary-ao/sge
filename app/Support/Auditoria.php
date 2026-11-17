<?php

namespace App\Support;

use App\Models\LogActividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Histórico básico de actividades (quem, o quê, quando, de onde).
 */
class Auditoria
{
    public static function registar(string $accao, string $descricao, ?Model $modelo = null, array $dados = [], ?int $utilizadorId = null): void
    {
        // Desactivação explícita (apenas usada por seeders de dados demonstrativos).
        if (config('sige.auditoria_suspensa')) {
            return;
        }
        $consola = app()->runningInConsole() && ! app()->runningUnitTests();
        // Operações de consola sem utilizador (ex.: seeders) não geram histórico.
        if ($consola && $utilizadorId === null && ! Auth::check()) {
            return;
        }

        try {
            $request = $consola ? null : request();

            LogActividade::create([
                'utilizador_id' => $utilizadorId ?? Auth::id(),
                'escola_id' => static::escolaDoContexto($modelo),
                'accao' => $accao,
                'descricao' => mb_substr($descricao, 0, 255),
                'modelo_tipo' => $modelo ? class_basename($modelo) : null,
                'modelo_id' => $modelo?->getKey(),
                'dados' => $dados ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            // O registo de auditoria nunca deve interromper a operação principal.
            report($e);
        }
    }

    protected static function escolaDoContexto(?Model $modelo): ?int
    {
        if ($modelo && $modelo->getAttribute('escola_id')) {
            return (int) $modelo->getAttribute('escola_id');
        }
        if (Auth::check()) {
            return app(ContextoEscola::class)->escolaActivaId();
        }

        return null;
    }
}
