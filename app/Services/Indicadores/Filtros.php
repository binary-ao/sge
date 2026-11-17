<?php

namespace App\Services\Indicadores;

use App\Models\AnoLectivo;
use Illuminate\Http\Request;

/**
 * Filtros globais dos painéis e relatórios. Todos os valores são validados
 * (inteiros ou valores de uma lista fechada) antes de chegarem às consultas.
 */
class Filtros
{
    public function __construct(
        public ?int $municipioId = null,
        public ?int $escolaId = null,
        public ?int $tipologiaId = null,
        public ?int $nivelId = null,
        public ?string $natureza = null,
        public ?int $anoLectivoId = null,
        public ?int $classeId = null,
    ) {
        $this->anoLectivoId ??= AnoLectivo::actual()?->id;
    }

    public static function doPedido(Request $request): self
    {
        $int = fn (string $k) => $request->filled($k) && ctype_digit((string) $request->input($k)) ? (int) $request->input($k) : null;
        $natureza = in_array($request->input('natureza'), ['publica', 'publico_privada', 'privada'], true) ? $request->input('natureza') : null;

        return new self($int('municipio_id'), $int('escola_id'), $int('tipologia_id'), $int('nivel_id'), $natureza, $int('ano_lectivo_id'), $int('classe_id'));
    }

    public function comMunicipio(?int $municipioId): self
    {
        $c = clone $this;
        $c->municipioId = $municipioId;

        return $c;
    }

    /** Parâmetros activos (para links e exportações). */
    public function paraUrl(): array
    {
        return array_filter([
            'municipio_id' => $this->municipioId, 'escola_id' => $this->escolaId, 'tipologia_id' => $this->tipologiaId,
            'nivel_id' => $this->nivelId, 'natureza' => $this->natureza, 'ano_lectivo_id' => $this->anoLectivoId, 'classe_id' => $this->classeId,
        ], fn ($v) => $v !== null);
    }

    public function activos(): int
    {
        return count(array_diff_key($this->paraUrl(), ['ano_lectivo_id' => 1]));
    }
}
