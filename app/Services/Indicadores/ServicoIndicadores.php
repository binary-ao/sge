<?php

namespace App\Services\Indicadores;

use App\Models\AnoLectivo;
use App\Models\Escola;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores da rede escolar e do desempenho académico, calculados a partir da base de dados.
 *
 * - O âmbito do utilizador é sempre aplicado (Escola::visiveis): a Direcção Municipal só
 *   vê o seu município, a escola só vê os seus dados.
 * - As taxas de aprovação resultam das regras de cada sistema de avaliação (resultados_finais);
 *   não se comparam médias numéricas entre escalas diferentes (0–10 vs 0–20).
 */
class ServicoIndicadores
{
    /** Situações finais consideradas positivas / negativas (as restantes ficam "pendentes"). */
    public const POSITIVAS = ['aprovado', 'transita', 'transita_condicionalmente', 'progressao_automatica'];
    public const NEGATIVAS = ['reprovado', 'retido', 'retido_faltas'];

    private array $cacheEscolas = [];

    /* ============================================================ Base */

    /** IDs das escolas no âmbito do utilizador que respeitam os filtros. */
    public function escolas(Filtros $f): array
    {
        $chave = md5(serialize([$f->municipioId, $f->escolaId, $f->tipologiaId, $f->nivelId, $f->natureza]));

        return $this->cacheEscolas[$chave] ??= Escola::visiveis()->activas()
            ->when($f->municipioId, fn ($q) => $q->where('municipio_id', $f->municipioId))
            ->when($f->escolaId, fn ($q) => $q->whereKey($f->escolaId))
            ->when($f->tipologiaId, fn ($q) => $q->where('tipologia_id', $f->tipologiaId))
            ->when($f->natureza, fn ($q) => $q->where('natureza', $f->natureza))
            ->when($f->nivelId, fn ($q) => $q->whereHas('niveisEnsino', fn ($n) => $n->where('niveis_ensino.id', $f->nivelId)))
            ->pluck('escolas.id')->map(fn ($i) => (int) $i)->all();
    }

    /** Matrículas do ano (activas ou concluídas) nas escolas filtradas. */
    private function matriculas(Filtros $f, ?int $anoId = null): Builder
    {
        return DB::table('matriculas as m')
            ->join('classes as c', 'c.id', '=', 'm.classe_id')
            ->join('escolas as e', 'e.id', '=', 'm.escola_id')
            ->whereIn('m.escola_id', $this->escolas($f) ?: [0])
            ->where('m.ano_lectivo_id', $anoId ?? $f->anoLectivoId)
            ->whereIn('m.estado', ['activa', 'concluida'])
            ->when($f->classeId, fn ($q) => $q->where('m.classe_id', $f->classeId))
            ->when($f->nivelId, fn ($q) => $q->where('c.nivel_ensino_id', $f->nivelId));
    }

    /** Resultados finais (situação do aluno) do ano indicado. */
    private function finais(Filtros $f, int $anoId): Builder
    {
        return $this->matriculas($f, $anoId)->join('resultados_finais as rf', 'rf.matricula_id', '=', 'm.id')
            ->where('rf.situacao', '!=', 'incompleto');
    }

    /* ========================================================== Totais */

    public function totais(Filtros $f): array
    {
        $ids = $this->escolas($f) ?: [0];

        return [
            'municipios' => Escola::whereIn('id', $ids)->distinct()->count('municipio_id'),
            'escolas' => count($this->escolas($f)),
            'alunos' => (clone $this->matriculas($f))->count(),
            'raparigas' => (clone $this->matriculas($f))->join('alunos as a', 'a.id', '=', 'm.aluno_id')->where('a.genero', 'F')->count(),
            'professores' => DB::table('professores')->whereIn('escola_id', $ids)->where('activo', true)->count(),
            'turmas' => DB::table('turmas as t')->join('classes as c', 'c.id', '=', 't.classe_id')
                ->whereIn('t.escola_id', $ids)->where('t.ano_lectivo_id', $f->anoLectivoId)
                ->when($f->classeId, fn ($q) => $q->where('t.classe_id', $f->classeId))
                ->when($f->nivelId, fn ($q) => $q->where('c.nivel_ensino_id', $f->nivelId))->count(),
        ];
    }

    /** Ano lectivo usado nos indicadores de desempenho: o seleccionado, se tiver resultados finais; senão o último com resultados. */
    public function anoDesempenho(Filtros $f): ?AnoLectivo
    {
        $comResultados = AnoLectivo::whereIn('id', DB::table('resultados_finais')->distinct()->pluck('ano_lectivo_id'))->recentes()->get();

        return $comResultados->firstWhere('id', $f->anoLectivoId)
            ?? $comResultados->first(fn ($a) => $a->data_inicio <= (AnoLectivo::find($f->anoLectivoId)?->data_inicio ?? now()))
            ?? $comResultados->first();
    }

    /** Taxas de aprovação / reprovação globais. */
    public function desempenho(Filtros $f, ?AnoLectivo $ano): array
    {
        if (! $ano) {
            return ['avaliados' => 0, 'aprovados' => 0, 'reprovados' => 0, 'pendentes' => 0, 'taxa_aprovacao' => null, 'taxa_reprovacao' => null, 'situacoes' => collect()];
        }
        $situacoes = $this->finais($f, $ano->id)->selectRaw('rf.situacao, count(*) as total')->groupBy('rf.situacao')->pluck('total', 'situacao');

        return $this->taxas($situacoes) + ['situacoes' => $situacoes];
    }

    private function taxas(Collection $situacoes): array
    {
        $aprov = (int) $situacoes->only(self::POSITIVAS)->sum();
        $reprov = (int) $situacoes->only(self::NEGATIVAS)->sum();
        $total = (int) $situacoes->sum();

        return [
            'avaliados' => $total,
            'aprovados' => $aprov,
            'reprovados' => $reprov,
            'pendentes' => $total - $aprov - $reprov,
            'taxa_aprovacao' => $total ? round($aprov / $total * 100, 1) : null,
            'taxa_reprovacao' => $total ? round($reprov / $total * 100, 1) : null,
        ];
    }

    /** Taxas por uma dimensão (escola, município, nível, tipologia). */
    public function desempenhoPor(Filtros $f, ?AnoLectivo $ano, string $dimensao): Collection
    {
        if (! $ano) {
            return collect();
        }
        [$coluna, $join] = match ($dimensao) {
            'escola' => ['e.id', null],
            'municipio' => ['e.municipio_id', null],
            'tipologia' => ['e.tipologia_id', null],
            'nivel' => ['c.nivel_ensino_id', null],
        };
        $linhas = $this->finais($f, $ano->id)
            ->selectRaw("$coluna as chave, rf.situacao, count(*) as total")
            ->groupBy('chave', 'rf.situacao')->get()->groupBy('chave');

        $nomes = $this->nomes($dimensao, $linhas->keys()->all());

        return $linhas->map(fn ($g, $chave) => ['id' => (int) $chave, 'nome' => $nomes[$chave] ?? '—']
            + $this->taxas($g->pluck('total', 'situacao')))
            ->sortByDesc('taxa_aprovacao')->values();
    }

    /* ==================================================== Rede escolar */

    public function escolasPor(Filtros $f, string $dimensao): Collection
    {
        $ids = $this->escolas($f) ?: [0];
        if ($dimensao === 'nivel') {
            // Uma escola pode ministrar vários níveis: a soma pode exceder o total.
            $dados = DB::table('escola_nivel_ensino')->whereIn('escola_id', $ids)
                ->selectRaw('nivel_ensino_id as chave, count(*) as total')->groupBy('chave')->pluck('total', 'chave');
        } else {
            $coluna = ['tipologia' => 'tipologia_id', 'municipio' => 'municipio_id', 'natureza' => 'natureza'][$dimensao];
            $dados = DB::table('escolas')->whereIn('id', $ids)->selectRaw("$coluna as chave, count(*) as total")->groupBy('chave')->pluck('total', 'chave');
        }

        return $this->comNomes($dimensao, $dados);
    }

    public function alunosPor(Filtros $f, string $dimensao): Collection
    {
        $coluna = ['nivel' => 'c.nivel_ensino_id', 'tipologia' => 'e.tipologia_id', 'municipio' => 'e.municipio_id', 'escola' => 'e.id', 'classe' => 'c.id'][$dimensao];
        $dados = $this->matriculas($f)->selectRaw("$coluna as chave, count(*) as total")->groupBy('chave')->pluck('total', 'chave');

        return $this->comNomes($dimensao, $dados);
    }

    public function professoresPor(Filtros $f, string $dimensao): Collection
    {
        $coluna = ['municipio' => 'e.municipio_id', 'tipologia' => 'e.tipologia_id', 'escola' => 'e.id'][$dimensao];
        $dados = DB::table('professores as p')->join('escolas as e', 'e.id', '=', 'p.escola_id')
            ->whereIn('p.escola_id', $this->escolas($f) ?: [0])->where('p.activo', true)
            ->selectRaw("$coluna as chave, count(*) as total")->groupBy('chave')->pluck('total', 'chave');

        return $this->comNomes($dimensao, $dados);
    }

    public function turmasPor(Filtros $f, string $dimensao): Collection
    {
        $coluna = ['municipio' => 'e.municipio_id', 'escola' => 'e.id'][$dimensao];
        $dados = DB::table('turmas as t')->join('escolas as e', 'e.id', '=', 't.escola_id')->join('classes as c', 'c.id', '=', 't.classe_id')
            ->whereIn('t.escola_id', $this->escolas($f) ?: [0])->where('t.ano_lectivo_id', $f->anoLectivoId)
            ->when($f->nivelId, fn ($q) => $q->where('c.nivel_ensino_id', $f->nivelId))
            ->selectRaw("$coluna as chave, count(*) as total")->groupBy('chave')->pluck('total', 'chave');

        return $this->comNomes($dimensao, $dados);
    }

    /* ============================================ Classificações e anos */

    /**
     * Distribuição das classificações finais por categoria qualitativa, separada por escala
     * (as categorias são comparáveis; os valores numéricos de escalas diferentes não).
     */
    public function distribuicaoClassificacoes(Filtros $f, ?AnoLectivo $ano): Collection
    {
        if (! $ano) {
            return collect();
        }

        return $this->matriculas($f, $ano->id)
            ->join('resultados_avaliacao as ra', 'ra.matricula_id', '=', 'm.id')
            ->join('configuracoes_avaliacao as ca', 'ca.id', '=', 'ra.configuracao_avaliacao_id')
            ->join('escalas_classificacao as es', 'es.id', '=', 'ca.escala_id')
            ->where('ra.periodo', 0)->whereNotNull('ra.classificacao_qualitativa')
            ->selectRaw('es.nome as escala, ra.classificacao_qualitativa as categoria, count(*) as total')
            ->groupBy('escala', 'categoria')->get()->groupBy('escala');
    }

    /**
     * Comparação entre anos lectivos: % de classificações positivas por trimestre
     * (permite comparar o ano em curso com anos anteriores no mesmo momento) e taxa de aprovação final.
     */
    public function comparacaoAnos(Filtros $f): Collection
    {
        return AnoLectivo::orderBy('data_inicio')->get()->map(function ($ano) use ($f) {
            $base = $this->matriculas($f, $ano->id)->join('resultados_avaliacao as ra', 'ra.matricula_id', '=', 'm.id')
                ->whereIn('ra.situacao', ['positiva', 'negativa']);
            $porTrimestre = (clone $base)->selectRaw("ra.periodo, sum(ra.situacao = 'positiva') as pos, count(*) as total")
                ->groupBy('ra.periodo')->get()->keyBy('periodo');
            $pct = fn ($p) => isset($porTrimestre[$p]) && $porTrimestre[$p]->total ? round($porTrimestre[$p]->pos / $porTrimestre[$p]->total * 100, 1) : null;
            $final = $this->desempenho($f, $ano);

            return [
                'ano' => $ano->designacao, 'estado' => $ano->estado,
                't1' => $pct(1), 't2' => $pct(2), 't3' => $pct(3),
                'aprovacao' => $final['taxa_aprovacao'], 'alunos' => (clone $this->matriculas($f, $ano->id))->count(),
            ];
        })->filter(fn ($l) => $l['alunos'] > 0)->values();
    }

    /* ================================================ Tabela de escolas */

    public function tabelaEscolas(Filtros $f, ?AnoLectivo $anoDesempenho): Collection
    {
        $ids = $this->escolas($f);
        $alunos = $this->alunosPor($f, 'escola')->pluck('total', 'id');
        $profs = $this->professoresPor($f, 'escola')->pluck('total', 'id');
        $taxas = $this->desempenhoPor($f, $anoDesempenho, 'escola')->keyBy('id');

        return Escola::with('municipio', 'tipologia', 'niveisEnsino')->whereIn('id', $ids ?: [0])->orderBy('nome')->get()
            ->map(fn ($e) => [
                'escola' => $e,
                'alunos' => (int) ($alunos[$e->id] ?? 0),
                'professores' => (int) ($profs[$e->id] ?? 0),
                'taxa_aprovacao' => $taxas[$e->id]['taxa_aprovacao'] ?? null,
                'avaliados' => $taxas[$e->id]['avaliados'] ?? 0,
            ]);
    }

    /* ======================================================== Auxiliares */

    private function nomes(string $dimensao, array $chaves): array
    {
        if (! $chaves) {
            return [];
        }

        return match ($dimensao) {
            'escola' => DB::table('escolas')->whereIn('id', $chaves)->pluck('nome', 'id')->all(),
            'municipio' => DB::table('municipios')->whereIn('id', $chaves)->pluck('nome', 'id')->all(),
            'tipologia' => DB::table('tipologias_escola')->whereIn('id', $chaves)->pluck('nome', 'id')->all(),
            'nivel' => DB::table('niveis_ensino')->whereIn('id', $chaves)->pluck('nome', 'id')->all(),
            'classe' => DB::table('classes')->whereIn('id', $chaves)->pluck('nome', 'id')->all(),
            'natureza' => Escola::NATUREZAS,
        };
    }

    /** Converte [chave => total] em lista ordenada [id, nome, total]. */
    private function comNomes(string $dimensao, Collection $dados): Collection
    {
        $nomes = $this->nomes($dimensao, $dados->keys()->all());
        $ordem = match ($dimensao) {
            'nivel' => DB::table('niveis_ensino')->pluck('ordem', 'id')->all(),
            'tipologia' => DB::table('tipologias_escola')->pluck('ordem', 'id')->all(),
            default => null,
        };

        $lista = $dados->map(fn ($total, $chave) => ['id' => is_numeric($chave) ? (int) $chave : $chave, 'nome' => $nomes[$chave] ?? (string) $chave, 'total' => (int) $total])->values();

        return $ordem ? $lista->sortBy(fn ($l) => $ordem[$l['id']] ?? 999)->values() : $lista->sortByDesc('total')->values();
    }
}
