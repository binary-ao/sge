<x-app-layout titulo="Painel da Escola">
    @php
        $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '').'%';
        $periodos = [1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre'];
    @endphp
    <x-cabecalho :titulo="$escola->nome" :subtitulo="$escola->tipologia->nome.' · '.$escola->municipio->nome.' · '.$escola->natureza_label"
        :migalhas="['Painéis' => null, 'Painel da Escola' => null]">
        <form method="GET" class="d-flex gap-2">
            <select name="ano_lectivo_id" class="form-select form-select-sm" data-auto-submit>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($ano?->id === $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </form>
        @can('pautas.ver')<a href="{{ route('avaliacao.pautas.index', ['ano_lectivo_id' => $ano?->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-table me-1"></i>Pautas</a>@endcan
    </x-cabecalho>

    <div class="d-flex flex-wrap gap-1 mb-3">
        @foreach ($escola->niveisEnsino as $n)<span class="badge badge-suave">{{ $n->nome }}</span>@endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2"><x-cartao-estatistico rotulo="Alunos matriculados" :valor="$totais['alunos']" icone="people" :href="auth()->user()->can('alunos.ver') ? route('alunos.index') : null" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-cartao-estatistico rotulo="Professores" :valor="$totais['professores']" icone="person-badge" tom="info" :href="auth()->user()->can('professores.ver') ? route('professores.index') : null" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-cartao-estatistico rotulo="Turmas" :valor="$totais['turmas']" icone="grid-3x3-gap" :href="auth()->user()->can('turmas.ver') ? route('turmas.index') : null" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-cartao-estatistico rotulo="Matrículas do ano" :valor="$matriculasAno" :detalhe="$novas.' novas'.($semTurma ? ' · '.$semTurma.' sem turma' : '')" icone="journal-check" tom="alerta" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-cartao-estatistico rotulo="Taxa de aprovação" :valor="$pct($desempenho['taxa_aprovacao'])" :detalhe="$anoDesempenho?->designacao" icone="graph-up-arrow" tom="sucesso" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-cartao-estatistico rotulo="Taxa de reprovação" :valor="$pct($desempenho['taxa_reprovacao'])" :detalhe="$anoDesempenho?->designacao" icone="graph-down-arrow" tom="erro" /></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Distribuição por género</h3></div>
                <div class="card-body"><div style="height: 230px"><canvas id="gGenero"></canvas></div></div></div>
        </div>
        <div class="col-xl-8 col-md-6">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Alunos por classe</h3></div>
                <div class="card-body"><div style="height: {{ max(230, $alunosPorClasse->count() * 30 + 40) }}px"><canvas id="gClasses"></canvas></div></div></div>
        </div>
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header"><h3 class="titulo">Desempenho por disciplina{{ $periodo ? ' — '.$periodos[$periodo].' de '.$ano?->designacao : '' }}</h3></div>
                <div class="card-body">
                    @if ($porDisciplina->isEmpty())
                        <p class="text-secondary small mb-0">Ainda não há classificações trimestrais neste ano lectivo.</p>
                    @else
                        <div style="height: {{ $porDisciplina->count() * 28 + 60 }}px"><canvas id="gDisciplinas"></canvas></div>
                        <p class="small text-secondary mt-2 mb-0">Percentagem de classificações positivas e negativas no período mais recente com resultados (entre parênteses, o número de classificações), ordenadas pela proporção de negativas.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Comparação entre anos lectivos</h3></div>
                <div class="card-body"><div style="height: 230px"><canvas id="gAnos"></canvas></div></div></div>
            </div>
        @if ($desempenhoNivel->count() > 1)
            <div class="col-xl-6">
                <div class="card h-100"><div class="card-header"><h3 class="titulo">Aprovação por nível de ensino — {{ $anoDesempenho?->designacao }}</h3></div>
                    <div class="card-body"><div style="height: {{ $desempenhoNivel->count() * 34 + 40 }}px"><canvas id="gNivel"></canvas></div></div></div>
            </div>
        @endif
        <div class="col-xl-{{ $desempenhoNivel->count() > 1 ? 6 : 12 }}">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Turmas — {{ $ano?->designacao }}</h3></div>
                <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Turma</th><th>Turno</th><th class="text-end">Alunos</th><th style="width:35%">Ocupação</th></tr></thead>
                    <tbody>
                    @forelse ($turmas as $t)
                        @php $oc = $t->capacidade ? round($t->matriculas_activas_count / $t->capacidade * 100) : 0; @endphp
                        <tr>
                            <td>@can('turmas.ver')<a href="{{ route('turmas.show', $t) }}">{{ $t->designacao }}</a>@else{{ $t->designacao }}@endcan</td>
                            <td class="small">{{ $t->turno->nome }}</td>
                            <td class="text-end">{{ $t->matriculas_activas_count }}/{{ $t->capacidade }}</td>
                            <td><div class="progress" style="height:6px"><div class="progress-bar {{ $oc >= 100 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ min(100, $oc) }}%"></div></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="tabela-vazia">Sem turmas.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    @php $kz = fn ($v) => \App\Services\Administrativo\ServicoPagamentos::kz((float) $v); @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Faltas nos últimos 30 dias" :valor="$faltas['ultimos30']" icone="calendar-x" tom="alerta"
            :href="auth()->user()->can('faltas.ver') ? route('faltas.index', ['de' => now()->subDays(30)->toDateString()]) : null" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Justificativos pendentes" :valor="$faltas['pendentes']" icone="hourglass-split" tom="info"
            :href="auth()->user()->can('faltas.ver') ? route('faltas.index', ['justificacao' => 'pendente']) : null" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Pagamentos do mês" :valor="$kz($financeiro['mes'])" :detalhe="$financeiro['recibosMes'].' recibo(s)'" icone="cash-stack" tom="sucesso"
            :href="auth()->user()->can('pagamentos.ver') ? route('pagamentos.index') : null" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Mensalidades em atraso" :valor="$kz($financeiro['atrasoValor'])" :detalhe="$financeiro['atrasoAlunos'].' aluno(s)'" icone="exclamation-triangle" tom="erro"
            :href="auth()->user()->can('pagamentos.ver') ? route('pagamentos.atraso') : null" /></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="titulo">Alunos com frequência abaixo de {{ $faltas['alerta'] }}%</h3>
            <span class="small text-secondary">A retenção por faltas aplica-se abaixo do mínimo definido na configuração de avaliação (2/3 das aulas).</span></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Aluno</th><th>Classe / Turma</th><th class="text-end">Frequência</th></tr></thead>
            <tbody>
            @forelse ($faltas['emRisco'] as $l)
                <tr>
                    <td>@can('alunos.ver')<a href="{{ route('alunos.show', $l['matricula']->aluno_id) }}">{{ $l['matricula']->aluno->nome }}</a>@else{{ $l['matricula']->aluno->nome }}@endcan</td>
                    <td class="small">{{ $l['matricula']->classe->nome }}{{ $l['matricula']->turma ? ' · '.$l['matricula']->turma->nome : '' }}</td>
                    <td class="text-end fw-semibold {{ $l['frequencia'] < 66.67 ? 'text-danger' : 'text-warning' }}">{{ number_format($l['frequencia'], 1, ',', '') }}%</td>
                </tr>
            @empty
                <tr><td colspan="3" class="tabela-vazia py-3">Nenhum aluno com frequência abaixo do limiar de alerta.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    @push('scripts')
        <script src="{{ asset('libs/chartjs/chart.umd.min.js') }}"></script>
        <script src="{{ asset('js/graficos.js') }}?v={{ config('sige.versao') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var G = SigeGraficos;
                var genero = @json($genero);
                G.circular('gGenero', [{nome: 'Feminino', total: genero.F || 0}, {nome: 'Masculino', total: genero.M || 0}], {cores: ['#1D6FB8', '#0B2A4A']});
                G.barras('gClasses', @json($alunosPorClasse), {legenda: 'aluno(s)'});
                var disc = @json($porDisciplina);
                var pct = function (d, campo) { var t = (+d.positivas) + (+d.negativas); return t ? Math.round(+d[campo] / t * 1000) / 10 : 0; };
                G.series('gDisciplinas', disc.map(function (d) { return d.nome + ' (' + ((+d.positivas) + (+d.negativas)) + ')'; }), [
                    {rotulo: 'Positivas', valores: disc.map(function (d) { return pct(d, 'positivas'); }), cor: G.VERDE},
                    {rotulo: 'Negativas', valores: disc.map(function (d) { return pct(d, 'negativas'); }), cor: G.VERMELHO}
                ], {empilhado: true, horizontal: true, sufixo: '%', max: 100});
                var anos = @json($comparacao);
                G.series('gAnos', anos.map(function (a) { return a.ano; }), [
                    {rotulo: 'Positivas I Trim.', valores: anos.map(function (a) { return a.t1; }), cor: '#6E9BC5'},
                    {rotulo: 'Aprovação final', valores: anos.map(function (a) { return a.aprovacao; }), cor: G.VERDE}
                ], {sufixo: '%', max: 100});
                G.barras('gNivel', @json($desempenhoNivel).map(function (l) { return {id: l.id, nome: l.nome, total: l.taxa_aprovacao}; }),
                    {sufixo: '%', max: 100, legenda: 'de aprovação', corPorValor: G.corTaxa});
            });
        </script>
    @endpush
</x-app-layout>
