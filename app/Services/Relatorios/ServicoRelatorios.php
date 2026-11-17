<?php

namespace App\Services\Relatorios;

use App\Models\AnoLectivo;
use App\Models\Matricula;
use App\Models\Turma;
use App\Services\Indicadores\Filtros;
use App\Services\Indicadores\ServicoIndicadores;
use App\Support\ContextoEscola;
use Illuminate\Support\Collection;

/**
 * Catálogo de relatórios (provinciais, municipais e escolares).
 * Cada relatório devolve colunas e linhas; as mesmas estruturas alimentam
 * a pré-visualização HTML, o PDF e o Excel. O âmbito do utilizador é sempre aplicado.
 */
class ServicoRelatorios
{
    public function __construct(private ServicoIndicadores $ind) {}

    /** chave => [grupo, título, descrição, permissões (qualquer), exige escola activa, filtros relevantes, disponível] */
    public static function catalogo(): array
    {
        $prov = 'relatorios.provinciais';
        $mun = 'relatorios.provinciais|relatorios.municipais';
        $esc = 'relatorios.escolares';
        $rede = ['municipio_id', 'tipologia_id', 'nivel_id', 'natureza', 'ano_lectivo_id'];

        return [
            'escolas-municipio' => ['Provinciais', 'Escolas por município', 'Número de escolas por município, por natureza.', $prov, false, $rede, true],
            'escolas-tipologia' => ['Provinciais', 'Escolas por tipologia', 'Número de escolas e alunos por tipologia institucional.', $prov, false, $rede, true],
            'escolas-nivel' => ['Provinciais', 'Escolas por nível de ensino', 'Escolas que ministram cada nível e respectivos alunos.', $prov, false, $rede, true],
            'alunos-municipio' => ['Provinciais', 'Alunos por município', 'Alunos matriculados por município, com distribuição por género.', $prov, false, $rede, true],
            'professores-municipio' => ['Provinciais', 'Professores por município', 'Professores activos e rácio alunos/professor por município.', $prov, false, $rede, true],
            'aprovacao' => ['Provinciais', 'Indicadores de aprovação', 'Taxas de aprovação e reprovação por município e por nível de ensino.', $prov, false, $rede, true],
            'comparacao-instituicoes' => ['Provinciais', 'Comparação entre instituições', 'Alunos, professores, rácio e taxa de aprovação por escola.', $prov, false, $rede, true],

            'escolas-do-municipio' => ['Municipais', 'Escolas do município', 'Identificação, tipologia, níveis e natureza das escolas.', $mun, false, $rede, true],
            'alunos-escola' => ['Municipais', 'Alunos por escola', 'Alunos matriculados por escola, com distribuição por género.', $mun, false, $rede, true],
            'professores-escola' => ['Municipais', 'Professores por escola', 'Professores activos por escola, por situação profissional.', $mun, false, $rede, true],
            'indicadores-academicos' => ['Municipais', 'Indicadores académicos', 'Taxa de aprovação e situação final dos alunos por escola.', $mun, false, $rede, true],

            'lista-alunos' => ['Escolares', 'Lista de alunos', 'Alunos matriculados, por turma.', $esc, true, ['ano_lectivo_id', 'turma_id'], true],
            'matriculas' => ['Escolares', 'Matrículas', 'Matrículas do ano lectivo por classe, tipo e estado.', $esc, true, ['ano_lectivo_id'], true],
            'pautas' => ['Escolares', 'Pautas', 'Pautas trimestrais e finais (módulo Pautas, com exportação PDF e Excel).', $esc, true, [], true],
            'faltas' => ['Escolares', 'Faltas', 'Faltas por aluno, turma, disciplina ou mês, com frequência.', $esc, true, ['ano_lectivo_id', 'turma_id', 'agrupamento', 'de', 'ate'], true],
            'pagamentos' => ['Escolares', 'Pagamentos', 'Valores recebidos por serviço e forma de pagamento no período.', $esc, true, ['de', 'ate'], true],
            'pagamentos-atraso' => ['Escolares', 'Pagamentos em atraso', 'Alunos com mensalidades em atraso e valor em dívida.', $esc, true, [], true],
            'recibos' => ['Escolares', 'Recibos', 'Recibos emitidos e anulados no período.', $esc, true, ['de', 'ate'], true],
            'declaracoes' => ['Escolares', 'Declarações', 'Declarações emitidas no período.', $esc, true, ['de', 'ate'], true],
        ];
    }

    /** @return array{titulo: string, colunas: array, linhas: Collection, notas: array} */
    public function gerar(string $chave, Filtros $f, array $extra = []): array
    {
        $ano = AnoLectivo::find($f->anoLectivoId);
        $anoDes = $this->ind->anoDesempenho($f);
        $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '').'%';
        $notas = ["Ano lectivo: {$ano?->designacao}."];

        $r = match ($chave) {
            'escolas-municipio' => (function () use ($f) {
                $por = $this->ind->escolasPor($f, 'municipio');
                $nat = \App\Models\Escola::whereIn('id', $this->ind->escolas($f) ?: [0])->get()->groupBy('municipio_id');

                return [['Município', 'Escolas', 'Públicas', 'Público-privadas', 'Privadas'],
                    $por->map(fn ($l) => [$l['nome'], $l['total'],
                        $nat[$l['id']]->where('natureza', 'publica')->count(), $nat[$l['id']]->where('natureza', 'publico_privada')->count(),
                        $nat[$l['id']]->where('natureza', 'privada')->count()])];
            })(),
            'escolas-tipologia' => (function () use ($f) {
                $alunos = $this->ind->alunosPor($f, 'tipologia')->pluck('total', 'id');
                $profs = $this->ind->professoresPor($f, 'tipologia')->pluck('total', 'id');

                return [['Tipologia institucional', 'Escolas', 'Alunos', 'Professores'],
                    $this->ind->escolasPor($f, 'tipologia')->map(fn ($l) => [$l['nome'], $l['total'], $alunos[$l['id']] ?? 0, $profs[$l['id']] ?? 0])];
            })(),
            'escolas-nivel' => (function () use ($f) {
                $alunos = $this->ind->alunosPor($f, 'nivel')->pluck('total', 'id');

                return [['Nível de ensino', 'Escolas que o ministram', 'Alunos matriculados'],
                    $this->ind->escolasPor($f, 'nivel')->map(fn ($l) => [$l['nome'], $l['total'], $alunos[$l['id']] ?? 0])];
            })(),
            'alunos-municipio' => [['Município', 'Alunos', 'Raparigas', 'Rapazes'], $this->porGenero($f, 'municipio')],
            'alunos-escola' => [['Escola', 'Alunos', 'Raparigas', 'Rapazes'], $this->porGenero($f, 'escola')],
            'professores-municipio' => (function () use ($f) {
                $alunos = $this->ind->alunosPor($f, 'municipio')->pluck('total', 'id');

                return [['Município', 'Professores activos', 'Alunos', 'Alunos por professor'],
                    $this->ind->professoresPor($f, 'municipio')->map(fn ($l) => [$l['nome'], $l['total'], $alunos[$l['id']] ?? 0,
                        $l['total'] ? number_format(($alunos[$l['id']] ?? 0) / $l['total'], 1, ',', '') : '—'])];
            })(),
            'professores-escola' => (function () use ($f) {
                $linhas = \Illuminate\Support\Facades\DB::table('professores as p')->join('escolas as e', 'e.id', '=', 'p.escola_id')
                    ->whereIn('p.escola_id', $this->ind->escolas($f) ?: [0])->where('p.activo', true)
                    ->selectRaw("e.nome, count(*) as total, sum(p.situacao_profissional = 'efectivo') as efectivos, sum(p.situacao_profissional = 'contratado') as contratados, sum(p.genero = 'F') as mulheres")
                    ->groupBy('e.nome')->orderBy('e.nome')->get();

                return [['Escola', 'Professores', 'Efectivos', 'Contratados', 'Mulheres'],
                    $linhas->map(fn ($l) => [$l->nome, $l->total, $l->efectivos, $l->contratados, $l->mulheres])];
            })(),
            'aprovacao' => (function () use ($f, $anoDes, $pct) {
                $linhas = collect();
                foreach (['municipio' => 'Município', 'nivel' => 'Nível de ensino'] as $dim => $rotulo) {
                    foreach ($this->ind->desempenhoPor($f, $anoDes, $dim) as $l) {
                        $linhas->push([$rotulo, $l['nome'], $l['avaliados'], $l['aprovados'], $l['reprovados'], $pct($l['taxa_aprovacao']), $pct($l['taxa_reprovacao'])]);
                    }
                }

                return [['Dimensão', 'Categoria', 'Avaliados', 'Aprovados', 'Reprovados', 'Taxa de aprovação', 'Taxa de reprovação'], $linhas];
            })(),
            'comparacao-instituicoes' => (function () use ($f, $anoDes, $pct) {
                return [['Escola', 'Município', 'Tipologia', 'Natureza', 'Alunos', 'Professores', 'Alunos/professor', 'Taxa de aprovação'],
                    $this->ind->tabelaEscolas($f, $anoDes)->map(fn ($l) => [$l['escola']->nome, $l['escola']->municipio->nome, $l['escola']->tipologia->nome,
                        $l['escola']->natureza_label, $l['alunos'], $l['professores'],
                        $l['professores'] ? number_format($l['alunos'] / $l['professores'], 1, ',', '') : '—', $pct($l['taxa_aprovacao'])])];
            })(),
            'escolas-do-municipio' => [['Código', 'Escola', 'Município', 'Tipologia', 'Níveis ministrados', 'Natureza', 'Estado'],
                $this->ind->tabelaEscolas($f, null)->map(fn ($l) => [$l['escola']->codigo, $l['escola']->nome, $l['escola']->municipio->nome,
                    $l['escola']->tipologia->nome, $l['escola']->niveisEnsino->pluck('nome')->join('; '), $l['escola']->natureza_label, $l['escola']->estado_label])],
            'indicadores-academicos' => (function () use ($f, $anoDes, $pct) {
                return [['Escola', 'Avaliados', 'Aprovados', 'Reprovados', 'Pendentes', 'Taxa de aprovação'],
                    $this->ind->desempenhoPor($f, $anoDes, 'escola')->sortBy('nome')->map(fn ($l) => [$l['nome'], $l['avaliados'], $l['aprovados'], $l['reprovados'], $l['pendentes'], $pct($l['taxa_aprovacao'])])];
            })(),
            'lista-alunos' => (function () use ($f, $extra) {
                $m = Matricula::with('aluno', 'classe', 'turma', 'curso')->where('ano_lectivo_id', $f->anoLectivoId)->whereIn('estado', ['activa', 'concluida'])
                    ->when($extra['turma_id'] ?? null, fn ($q, $t) => $q->where('turma_id', $t))->get()
                    ->sortBy(fn ($m) => ($m->turma?->designacao ?? 'zz').$m->aluno->nome)->values();

                return [['N.º', 'Processo', 'Nome', 'Género', 'Data de nascimento', 'Classe', 'Turma', 'Encarregado'],
                    $m->map(fn ($x, $i) => [$i + 1, $x->aluno->numero_processo, $x->aluno->nome, $x->aluno->genero, $x->aluno->data_nascimento->format('d/m/Y'),
                        $x->classe->nome.($x->curso ? ' · '.$x->curso->codigo : ''), $x->turma?->nome ?? '—', $x->aluno->encarregado?->nome ?? '—'])];
            })(),
            'matriculas' => (function () use ($f) {
                $linhas = Matricula::with('classe')->where('ano_lectivo_id', $f->anoLectivoId)->get()->groupBy('classe_id')
                    ->map(fn ($g) => [$g->first()->classe->nome.' — '.$g->first()->classe->nivelEnsino->nome, $g->count(), $g->where('tipo', 'nova')->count(),
                        $g->where('tipo', 'confirmacao')->count(), $g->where('estado', 'activa')->count(), $g->whereIn('estado', ['transferida', 'desistente', 'anulada'])->count(),
                        $g->where('estado', 'activa')->whereNull('turma_id')->count()])->sortBy(0)->values();

                return [['Classe', 'Total', 'Novas', 'Confirmações', 'Activas', 'Saídas / anuladas', 'Sem turma'], $linhas];
            })(),
            'faltas' => $this->faltas($f, $extra),
            'pagamentos' => $this->pagamentos($extra),
            'pagamentos-atraso' => $this->pagamentosAtraso($f),
            'recibos' => $this->recibos($extra),
            'declaracoes' => $this->declaracoes($extra),
            default => abort(404),
        };
        if (in_array($chave, ['pagamentos', 'recibos', 'declaracoes', 'faltas'], true)) {
            [$de, $ate] = $this->periodo($extra, $chave === 'faltas' ? $ano : null);
            $notas[] = 'Período: '.\Illuminate\Support\Carbon::parse($de)->format('d/m/Y').' a '.\Illuminate\Support\Carbon::parse($ate)->format('d/m/Y').'.';
        }

        if (in_array($chave, ['aprovacao', 'comparacao-instituicoes', 'indicadores-academicos'], true)) {
            $notas[] = 'Indicadores de desempenho: '.($anoDes ? "ano lectivo {$anoDes->designacao} (último com resultados finais)" : 'sem resultados finais').'.';
            $notas[] = 'As taxas resultam das regras de cada sistema de avaliação; as médias numéricas de escalas diferentes não são comparadas.';
        }
        $notas[] = 'Dados demonstrativos — não constituem estatísticas oficiais.';

        return ['titulo' => self::catalogo()[$chave][1], 'colunas' => $r[0], 'linhas' => collect($r[1])->values(), 'notas' => $notas];
    }

    /** Período [de, até]: por omissão, o ano lectivo (faltas) ou o mês corrente (financeiro). */
    private function periodo(array $extra, ?AnoLectivo $ano = null): array
    {
        $padraoDe = $ano ? $ano->data_inicio->toDateString() : now()->startOfMonth()->toDateString();
        $padraoAte = $ano ? $ano->data_fim->min(now())->toDateString() : now()->toDateString();

        return [$extra['de'] ?? $padraoDe, $extra['ate'] ?? $padraoAte];
    }

    private function faltas(Filtros $f, array $extra): array
    {
        [$de, $ate] = $this->periodo($extra, AnoLectivo::find($f->anoLectivoId));
        $faltas = \App\Models\Falta::with('matricula.aluno', 'turma.classe', 'disciplina')
            ->whereHas('matricula', fn ($q) => $q->where('ano_lectivo_id', $f->anoLectivoId))
            ->when($extra['turma_id'] ?? null, fn ($q, $t) => $q->where('turma_id', $t))
            ->whereBetween('data', [$de, $ate])->get();
        $agrupamento = in_array($extra['agrupamento'] ?? null, ['aluno', 'turma', 'disciplina', 'mes'], true) ? $extra['agrupamento'] : 'aluno';
        $soma = fn ($g) => [$g->count(), (int) $g->sum('tempos'), (int) $g->where('justificacao', 'aprovado')->sum('tempos'),
            (int) $g->where('justificacao', '!=', 'aprovado')->sum('tempos'), $g->where('justificacao', 'pendente')->count()];
        $cab = ['Faltas', 'Tempos', 'Justificados', 'Não justificados', 'Justificativos pendentes'];

        if ($agrupamento === 'aluno') {
            $frequencia = app(\App\Services\Administrativo\ServicoFrequencia::class);
            $linhas = $faltas->groupBy('matricula_id')->map(function ($g) use ($soma, $frequencia) {
                $m = $g->first()->matricula;
                $freq = $frequencia->percentagem($m);

                return array_merge([$m->aluno->nome, $g->first()->turma->designacao], $soma($g), [$freq === null ? '—' : number_format($freq, 1, ',', '').'%']);
            })->sortBy(0)->values();

            return [array_merge(['Aluno', 'Turma'], $cab, ['Frequência']), $linhas];
        }
        $chave = match ($agrupamento) {
            'turma' => fn ($x) => $x->turma->designacao,
            'disciplina' => fn ($x) => $x->disciplina->nome,
            'mes' => fn ($x) => $x->data->format('Y-m'),
        };
        $rotulo = ['turma' => 'Turma', 'disciplina' => 'Disciplina', 'mes' => 'Mês'][$agrupamento];
        $linhas = $faltas->groupBy($chave)->sortKeys()->map(fn ($g, $k) => array_merge([
            $agrupamento === 'mes' ? \App\Models\Pagamento::MESES[(int) substr($k, 5)].' '.substr($k, 0, 4) : $k,
        ], $soma($g)))->values();

        return [array_merge([$rotulo], $cab), $linhas];
    }

    private function pagamentos(array $extra): array
    {
        [$de, $ate] = $this->periodo($extra);
        $linhas = \App\Models\Pagamento::with('servico')->where('estado', 'valido')->whereBetween('data_pagamento', [$de, $ate])->get()
            ->groupBy(fn ($p) => $p->servico->nome.'|'.$p->forma_pagamento)
            ->map(function ($g, $k) {
                [$servico, $forma] = explode('|', $k);

                return [$servico, config('administrativo.pagamentos.formas.'.$forma), $g->count(), number_format($g->sum('valor'), 2, ',', ' ')];
            })->sortBy(0)->values();

        return [['Serviço', 'Forma de pagamento', 'Pagamentos', 'Valor (Kz)'], $linhas];
    }

    private function pagamentosAtraso(Filtros $f): array
    {
        $lista = app(\App\Services\Administrativo\ServicoPagamentos::class)->emAtraso($f->anoLectivoId);

        return [['Aluno', 'Processo', 'Classe / Turma', 'Encarregado', 'Telefone', 'Meses em atraso', 'N.º meses', 'Valor (Kz)'],
            $lista->map(fn ($l) => [
                $l['matricula']->aluno->nome, $l['matricula']->aluno->numero_processo,
                $l['matricula']->classe->nome.($l['matricula']->turma ? ' '.$l['matricula']->turma->nome : ''),
                $l['matricula']->aluno->encarregado?->nome ?? '—', $l['matricula']->aluno->encarregado?->telefone ?? '—',
                $l['meses']->pluck('rotulo')->join(', '), $l['meses']->count(), number_format($l['meses']->sum('valor'), 2, ',', ' '),
            ])];
    }

    private function recibos(array $extra): array
    {
        [$de, $ate] = $this->periodo($extra);
        $linhas = \App\Models\Recibo::with('aluno', 'emitidoPor')->whereBetween('data', [$de, $ate])->orderBy('ano')->orderBy('sequencial')->get()
            ->map(fn ($r) => [$r->numero, $r->data->format('d/m/Y'), $r->aluno->nome, $r->forma_label, number_format($r->total, 2, ',', ' '),
                $r->estado === 'anulado' ? 'Anulado: '.$r->motivo_anulacao : 'Emitido', $r->emitidoPor?->nome ?? '—']);

        return [['Recibo', 'Data', 'Aluno', 'Forma', 'Total (Kz)', 'Estado', 'Emitido por'], $linhas];
    }

    private function declaracoes(array $extra): array
    {
        [$de, $ate] = $this->periodo($extra);
        $linhas = \App\Models\Declaracao::with('aluno', 'emitidaPor')->whereDate('created_at', '>=', $de)->whereDate('created_at', '<=', $ate)->orderBy('id')->get()
            ->map(fn ($d) => [$d->numero, $d->created_at->format('d/m/Y'), $d->tipo_label, $d->aluno->nome, $d->finalidade ?? '—', $d->codigo_verificacao, $d->emitidaPor?->nome ?? '—']);

        return [['Número', 'Data', 'Tipo', 'Aluno', 'Finalidade', 'Código', 'Emitida por'], $linhas];
    }

    private function porGenero(Filtros $f, string $dimensao): Collection
    {
        $coluna = $dimensao === 'municipio' ? 'mu.nome' : 'e.nome';
        $q = \Illuminate\Support\Facades\DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('escolas as e', 'e.id', '=', 'm.escola_id')->join('municipios as mu', 'mu.id', '=', 'e.municipio_id')
            ->join('classes as c', 'c.id', '=', 'm.classe_id')
            ->whereIn('m.escola_id', $this->ind->escolas($f) ?: [0])->where('m.ano_lectivo_id', $f->anoLectivoId)->whereIn('m.estado', ['activa', 'concluida'])
            ->when($f->nivelId, fn ($q) => $q->where('c.nivel_ensino_id', $f->nivelId))
            ->selectRaw("$coluna as nome, count(*) as total, sum(a.genero = 'F') as f, sum(a.genero = 'M') as mm")->groupBy('nome')->orderBy('nome')->get();

        return $q->map(fn ($l) => [$l->nome, $l->total, $l->f, $l->mm]);
    }

    public static function turmasDaEscola(?int $anoId): Collection
    {
        return app(ContextoEscola::class)->escolaActivaId() ? Turma::with('classe', 'curso')->where('ano_lectivo_id', $anoId)->get()->sortBy('designacao') : collect();
    }
}
