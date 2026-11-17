<?php

namespace App\Services\Avaliacao;

use App\Models\AnoLectivo;
use App\Models\ConfiguracaoAvaliacao;
use App\Models\HistoricoConfiguracao;
use App\Models\ModeloFormula;
use Illuminate\Support\Facades\DB;

/**
 * Gravação versionada das configurações de avaliação.
 * Cada gravação incrementa a versão e guarda uma cópia completa (snapshot)
 * com o utilizador, a data e o motivo da alteração.
 */
class GestorConfiguracoes
{
    /**
     * $dados: campos da configuração + 'classes' (ids) + 'componentes' + 'formulas' + 'regras' (listas).
     */
    public function guardar(ConfiguracaoAvaliacao $config, array $dados, ?string $motivo, ?string $accao = null): ConfiguracaoAvaliacao
    {
        // Nenhuma configuração incoerente é gravada, qualquer que seja a origem (interface, seeders, duplicação).
        $erros = DefinicaoAvaliacao::deArray($dados, \App\Models\EscalaClassificacao::findOrFail($dados['escala_id']))->validar();
        if ($erros) {
            throw \Illuminate\Validation\ValidationException::withMessages($erros);
        }

        return DB::transaction(function () use ($config, $dados, $motivo, $accao) {
            $nova = ! $config->exists;
            $campos = array_intersect_key($dados, array_flip([
                'sistema_avaliacao_id', 'ano_lectivo_id', 'nivel_ensino_id', 'ciclo_id', 'disciplina_id', 'escala_id',
                'nome', 'descricao', 'tipo_classificacao', 'nota_minima_aprovacao', 'arredondamento',
                'variavel_trimestral', 'variavel_final', 'estado',
            ]));
            $campos['variavel_trimestral'] = strtoupper($campos['variavel_trimestral']);
            $campos['variavel_final'] = strtoupper($campos['variavel_final']);

            $config->fill($campos);
            $config->versao = $nova ? 1 : $config->versao + 1;
            $config->actualizado_por = auth()->id();
            if ($nova) {
                $config->criado_por = auth()->id();
            }
            $config->save();

            $config->classes()->sync($dados['classes'] ?? []);

            $config->componentes()->delete();
            foreach (array_values($dados['componentes'] ?? []) as $i => $c) {
                $config->componentes()->create([
                    'codigo' => strtoupper($c['codigo']), 'nome' => $c['nome'], 'tipo' => $c['tipo'], 'momento' => $c['momento'],
                    'trimestres' => $c['momento'] === 'trimestral' ? DefinicaoAvaliacao::normalizarTrimestres($c['trimestres'] ?? null) : null,
                    'itens_previstos' => max(1, (int) ($c['itens_previstos'] ?? 1)),
                    'nota_minima' => $c['nota_minima'] ?? 0, 'nota_maxima' => $c['nota_maxima'], 'ordem' => $i + 1,
                ]);
            }

            $config->formulas()->delete();
            foreach (array_values($dados['formulas'] ?? []) as $i => $f) {
                $config->formulas()->create([
                    'codigo' => strtoupper($f['codigo']), 'nome' => $f['nome'], 'expressao' => trim($f['expressao']),
                    'nivel' => $f['nivel'], 'trimestres' => $f['nivel'] === 'trimestral' ? DefinicaoAvaliacao::normalizarTrimestres($f['trimestres'] ?? null) : null,
                    'arredondar' => ! empty($f['arredondar']), 'ordem' => $i + 1,
                    'modelo_formula_id' => $f['modelo_formula_id'] ?? null,
                ]);
            }

            $config->regras()->delete();
            foreach (array_values($dados['regras'] ?? []) as $i => $r) {
                $config->regras()->create([
                    'tipo' => $r['tipo'], 'parametros' => $r['parametros'] ?? [], 'activo' => ! empty($r['activo']), 'ordem' => $i + 1,
                ]);
            }

            $config->unsetRelations();
            HistoricoConfiguracao::registar($config, 'configuracao', $accao ?? ($nova ? 'criacao' : 'alteracao'), $motivo);

            return $config;
        });
    }

    /** Duplica uma configuração para outro ano lectivo (fica em rascunho). */
    public function duplicar(ConfiguracaoAvaliacao $origem, AnoLectivo $ano, ?string $motivo = null): ConfiguracaoAvaliacao
    {
        $snap = $origem->snapshot();
        $dados = $snap['campos'];
        $dados['ano_lectivo_id'] = $ano->id;
        $dados['estado'] = 'rascunho';
        $dados['nome'] = preg_replace('/\s*—\s*\d{4}(\/\d{4})?$/', '', $dados['nome']).' — '.$ano->designacao;

        return $this->guardar(new ConfiguracaoAvaliacao, $dados + [
            'classes' => $snap['classes'],
            'componentes' => $snap['componentes'],
            'formulas' => $snap['formulas'],
            'regras' => $snap['regras'],
        ], $motivo ?? "Duplicada de \"{$origem->nome}\" (versão {$origem->versao}).", 'duplicacao');
    }

    public function alterarEstado(ConfiguracaoAvaliacao $config, string $estado, ?string $motivo): void
    {
        DB::transaction(function () use ($config, $estado, $motivo) {
            $config->forceFill(['estado' => $estado, 'versao' => $config->versao + 1, 'actualizado_por' => auth()->id()])->save();
            HistoricoConfiguracao::registar($config, 'configuracao', $estado === 'activa' ? 'activacao' : 'desactivacao', $motivo);
        });
    }

    public function guardarModelo(ModeloFormula $modelo, array $dados, ?string $motivo): ModeloFormula
    {
        return DB::transaction(function () use ($modelo, $dados, $motivo) {
            $nova = ! $modelo->exists;
            $modelo->fill($dados);
            $modelo->versao = $nova ? 1 : $modelo->versao + 1;
            $modelo->save();
            HistoricoConfiguracao::registar($modelo, 'modelo_formula', $nova ? 'criacao' : 'alteracao', $motivo);

            return $modelo;
        });
    }
}
