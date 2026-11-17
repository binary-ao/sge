<?php

namespace App\Services\Avaliacao;

use App\Models\ConfiguracaoAvaliacao;

/**
 * Determina a configuração de avaliação aplicável a um ano lectivo, classe e disciplina.
 * Uma configuração específica da disciplina tem prioridade sobre a geral da classe.
 */
class ServicoConfiguracao
{
    private array $cache = [];
    private array $definicoes = [];

    public function resolver(int $anoLectivoId, int $classeId, ?int $disciplinaId = null): ?ConfiguracaoAvaliacao
    {
        $chave = "$anoLectivoId:$classeId:".($disciplinaId ?? 0);
        if (array_key_exists($chave, $this->cache)) {
            return $this->cache[$chave];
        }

        $config = ConfiguracaoAvaliacao::activas()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $classeId))
            ->where(fn ($q) => $q->whereNull('disciplina_id')->when($disciplinaId, fn ($w) => $w->orWhere('disciplina_id', $disciplinaId)))
            ->orderByRaw('disciplina_id IS NULL')   // específica da disciplina primeiro
            ->orderByDesc('id')
            ->first();

        return $this->cache[$chave] = $config;
    }

    public function definicao(ConfiguracaoAvaliacao $config): DefinicaoAvaliacao
    {
        return $this->definicoes[$config->id.':'.$config->versao] ??= DefinicaoAvaliacao::deConfiguracao($config);
    }

    /** Verifica se já existe outra configuração activa para alguma das classes (mesmo ano e disciplina). */
    public function conflitos(ConfiguracaoAvaliacao $config, array $classeIds): array
    {
        return ConfiguracaoAvaliacao::activas()
            ->whereKeyNot($config->id ?? 0)
            ->where('ano_lectivo_id', $config->ano_lectivo_id)
            ->where(fn ($q) => $config->disciplina_id ? $q->where('disciplina_id', $config->disciplina_id) : $q->whereNull('disciplina_id'))
            ->whereHas('classes', fn ($q) => $q->whereIn('classes.id', $classeIds))
            ->pluck('nome')->all();
    }

    public function limpar(): void
    {
        $this->cache = [];
        $this->definicoes = [];
    }
}
