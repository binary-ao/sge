{{-- Conteúdo partilhado dos painéis provincial ($modo = 'provincial') e municipal ($modo = 'municipal'). --}}
@php
    $provincial = $modo === 'provincial';
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '').'%';
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $alt = fn ($lista, $min = 220) => max($min, count($lista) * 34 + 40);
    $urlEscolas = route('escolas.index', array_filter(['municipio_id' => $filtros->municipioId]));
    $urlEscola = url('escolas').'/';
@endphp

<div class="alert alert-light border small d-flex flex-wrap gap-3 align-items-center">
    <span><i class="bi bi-calendar3 me-1 text-primary"></i>Rede e matrículas: <strong>{{ $ano?->designacao }}</strong></span>
    <span><i class="bi bi-clipboard-check me-1 text-primary"></i>Desempenho: <strong>{{ $anoDesempenho?->designacao ?? 'sem resultados finais' }}</strong>
        @if ($anoDesempenho && $anoDesempenho->id !== $ano?->id)<span class="text-secondary">(último ano com resultados finais; o ano seleccionado ainda está em curso)</span>@endif</span>
    <span class="text-secondary"><i class="bi bi-info-circle me-1"></i>Dados demonstrativos, não oficiais.</span>
</div>

{{-- Indicadores principais --}}
<div class="row g-3 mb-4">
    @if ($provincial)
        <div class="col-6 col-md-4 col-xl"><x-cartao-estatistico rotulo="Municípios" :valor="$totais['municipios']" icone="map" /></div>
    @endif
    <div class="col-6 col-md-4 col-xl"><x-cartao-estatistico rotulo="Escolas" :valor="$totais['escolas']" icone="building" tom="info" :href="$urlEscolas" /></div>
    <div class="col-6 col-md-4 col-xl"><x-cartao-estatistico rotulo="Alunos matriculados" :valor="$n($totais['alunos'])" :detalhe="$totais['alunos'] ? round($totais['raparigas'] / $totais['alunos'] * 100).'% raparigas' : null" icone="people" /></div>
    <div class="col-6 col-md-4 col-xl"><x-cartao-estatistico rotulo="Professores" :valor="$n($totais['professores'])" :detalhe="$totais['professores'] ? number_format($totais['alunos'] / $totais['professores'], 1, ',', '').' alunos/professor' : null" icone="person-badge" tom="info" /></div>
    <div class="col-6 col-md-4 col-xl"><x-cartao-estatistico rotulo="Turmas" :valor="$n($totais['turmas'])" icone="grid-3x3-gap" /></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><x-cartao-estatistico rotulo="Taxa de aprovação" :valor="$pct($desempenho['taxa_aprovacao'])" :detalhe="$n($desempenho['aprovados']).' de '.$n($desempenho['avaliados']).' alunos avaliados em '.($anoDesempenho?->designacao ?? '—')" icone="graph-up-arrow" tom="sucesso" /></div>
    <div class="col-md-6"><x-cartao-estatistico rotulo="Taxa de reprovação" :valor="$pct($desempenho['taxa_reprovacao'])" :detalhe="$n($desempenho['reprovados']).' alunos'.($desempenho['pendentes'] ? ' · '.$n($desempenho['pendentes']).' em exame de recurso' : '')" icone="graph-down-arrow" tom="erro" /></div>
</div>

{{-- Rede escolar --}}
<h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-diagram-3 me-2"></i>Rede Escolar</h2>
<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Escolas por tipologia institucional</h3></div>
            <div class="card-body"><div style="height: {{ $alt($escolasTipologia) }}px"><canvas id="gTipologia"></canvas></div></div></div>
    </div>
    @if ($provincial)
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Escolas por município</h3></div>
                <div class="card-body"><div style="height: {{ $alt($escolasMunicipio) }}px"><canvas id="gMunicipio"></canvas></div></div></div>
        </div>
    @endif
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Alunos por nível de ensino</h3></div>
            <div class="card-body"><div style="height: {{ $alt($alunosNivel) }}px"><canvas id="gAlunosNivel"></canvas></div></div></div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Alunos por tipologia institucional</h3></div>
            <div class="card-body"><div style="height: {{ $alt($alunosTipologia) }}px"><canvas id="gAlunosTipologia"></canvas></div></div></div>
    </div>
    @if ($provincial)
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Professores por município</h3></div>
                <div class="card-body"><div style="height: {{ $alt($professoresMunicipio) }}px"><canvas id="gProfMunicipio"></canvas></div></div></div>
        </div>
    @endif
    <div class="col-xl-{{ $provincial ? 3 : 6 }} col-md-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Instituições por natureza</h3></div>
            <div class="card-body"><div style="height: 240px"><canvas id="gNatureza"></canvas></div></div></div>
    </div>
    <div class="col-xl-{{ $provincial ? 3 : 6 }} col-md-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Escolas por nível ministrado</h3></div>
            <ul class="list-group list-group-flush small">
                @foreach ($escolasNivel as $l)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route('escolas.index', array_filter(['nivel_id' => $l['id'], 'municipio_id' => $filtros->municipioId])) }}" class="text-decoration-none">{{ $l['nome'] }}</a><strong>{{ $l['total'] }}</strong>
                    </li>
                @endforeach
                <li class="list-group-item text-secondary">Uma escola pode ministrar vários níveis.</li>
            </ul>
        </div>
    </div>
    @if ($provincial)
        <div class="col-xl-6">
            <div class="card h-100"><div class="card-header"><h3 class="titulo">Turmas e professores</h3></div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Município</th><th class="text-end">Turmas</th><th class="text-end">Professores</th></tr></thead>
                    <tbody>
                    @php $profs = $professoresMunicipio->pluck('total', 'id'); @endphp
                    @foreach ($turmasMunicipio as $l)
                        <tr><td>{{ $l['nome'] }}</td><td class="text-end">{{ $l['total'] }}</td><td class="text-end">{{ $profs[$l['id']] ?? 0 }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body small border-top">
                    <div class="fw-semibold mb-1">Professores por tipologia</div>
                    @foreach ($professoresTipologia as $l)<span class="badge badge-suave me-1 mb-1">{{ $l['nome'] }}: {{ $l['total'] }}</span>@endforeach
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Desempenho académico --}}
<h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-mortarboard me-2"></i>Desempenho Académico <span class="fs-6 fw-normal text-secondary">· {{ $anoDesempenho?->designacao }}</span></h2>
<div class="alert alert-light border small"><i class="bi bi-shield-check me-1 text-primary"></i>
    As taxas de aprovação resultam das regras de cada sistema de avaliação (Pré-Escolar, Primário, Secundário…). As médias numéricas de escalas diferentes (0–10 e 0–20)
    <strong>não são comparadas directamente</strong>; a distribuição das classificações usa as categorias qualitativas de cada escala.
</div>
<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Taxa de aprovação por escola</h3><span class="small text-secondary">Clique para abrir a escola</span></div>
            <div class="card-body"><div style="height: {{ $alt($aprovacaoEscola) }}px"><canvas id="gAprovEscola"></canvas></div></div></div>
    </div>
    <div class="col-xl-6">
        <div class="row g-3">
            @if ($provincial)
                <div class="col-12">
                    <div class="card"><div class="card-header"><h3 class="titulo">Aprovação por município</h3></div>
                        <div class="card-body"><div style="height: {{ $alt($aprovacaoMunicipio, 180) }}px"><canvas id="gAprovMunicipio"></canvas></div></div></div>
                </div>
            @endif
            <div class="col-12">
                <div class="card"><div class="card-header"><h3 class="titulo">Desempenho por nível de ensino</h3></div>
                    <div class="card-body"><div style="height: {{ $alt($aprovacaoNivel, 180) }}px"><canvas id="gAprovNivel"></canvas></div></div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Desempenho por tipologia institucional</h3></div>
            <div class="card-body"><div style="height: {{ $alt($aprovacaoTipologia, 180) }}px"><canvas id="gAprovTipologia"></canvas></div></div></div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100"><div class="card-header"><h3 class="titulo">Comparação entre anos lectivos</h3></div>
            <div class="card-body">
                <div style="height: 240px"><canvas id="gAnos"></canvas></div>
                <p class="small text-secondary mt-2 mb-0">% de classificações positivas por trimestre (comparável no mesmo momento do ano) e taxa de aprovação final.
                    O ano em curso só tem os trimestres já lançados.</p>
            </div></div>
    </div>
    <div class="col-12">
        <div class="card"><div class="card-header"><h3 class="titulo">Distribuição das classificações finais</h3><span class="small text-secondary">Por escala de classificação</span></div>
            <div class="card-body"><div class="row g-3">
                @forelse ($distribuicao as $escala => $linhas)
                    <div class="col-lg-4"><div class="small fw-semibold mb-1">{{ $escala }}</div><div style="height: 220px"><canvas id="gDist{{ $loop->index }}"></canvas></div></div>
                @empty
                    <p class="text-secondary small mb-0">Sem classificações finais para os filtros indicados.</p>
                @endforelse
            </div></div></div>
    </div>
</div>

{{-- Instituições --}}
<div class="card mb-3">
    <div class="card-header"><h2 class="titulo">Instituições ({{ $tabela->count() }})</h2>
        <input type="search" class="form-control form-control-sm" style="max-width: 260px" placeholder="Pesquisar na tabela…" id="pesquisaTabela"></div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0" id="tabelaEscolas">
            <thead><tr><th>Escola</th><th>Município</th><th>Tipologia</th><th>Níveis ministrados</th><th>Natureza</th><th class="text-end">Alunos</th><th class="text-end">Professores</th><th class="text-end">Aprovação</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($tabela as $l)
                <tr>
                    <td><a href="{{ route('escolas.show', $l['escola']) }}" class="fw-semibold text-decoration-none">{{ $l['escola']->nome }}</a></td>
                    <td class="small">{{ $l['escola']->municipio->nome }}</td>
                    <td class="small">{{ $l['escola']->tipologia->nome }}</td>
                    <td class="small">{{ $l['escola']->niveisEnsino->pluck('nome')->join(', ') }}</td>
                    <td class="small">{{ $l['escola']->natureza_label }}</td>
                    <td class="text-end">{{ $n($l['alunos']) }}</td>
                    <td class="text-end">{{ $l['professores'] }}</td>
                    <td class="text-end fw-semibold" style="color: {{ $l['taxa_aprovacao'] === null ? '' : ($l['taxa_aprovacao'] >= 90 ? 'var(--sige-sucesso)' : ($l['taxa_aprovacao'] >= 60 ? 'var(--sige-alerta)' : 'var(--sige-erro)')) }}">{{ $pct($l['taxa_aprovacao']) }}</td>
                    <td><span class="estado text-{{ $l['escola']->estado_cor }}">{{ $l['escola']->estado_label }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="tabela-vazia">Nenhuma instituição corresponde aos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('libs/chartjs/chart.umd.min.js') }}"></script>
    <script src="{{ asset('js/graficos.js') }}?v={{ config('sige.versao') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var G = SigeGraficos;
            var aqui = window.location.href;
            var escolas = @json($urlEscolas);
            var urlEscola = @json($urlEscola);
            var taxa = function (lista) { return lista.map(function (l) { return {id: l.id, nome: l.nome, total: l.taxa_aprovacao}; }); };

            G.barras('gTipologia', @json($escolasTipologia), {legenda: 'escola(s)', onClick: function (d) { G.ir(escolas, {tipologia_id: d.id}); }});
            G.barras('gMunicipio', @json($escolasMunicipio), {legenda: 'escola(s)', cor: '#1D6FB8', onClick: function (d) { G.ir(aqui, {municipio_id: d.id}); }});
            G.barras('gAlunosNivel', @json($alunosNivel), {legenda: 'aluno(s)', onClick: function (d) { G.ir(aqui, {nivel_id: d.id}); }});
            G.barras('gAlunosTipologia', @json($alunosTipologia), {legenda: 'aluno(s)', cor: '#6E9BC5', onClick: function (d) { G.ir(aqui, {tipologia_id: d.id}); }});
            G.barras('gProfMunicipio', @json($professoresMunicipio), {legenda: 'professor(es)', cor: '#B7791F', onClick: function (d) { G.ir(aqui, {municipio_id: d.id}); }});
            G.circular('gNatureza', @json($escolasNatureza), {onClick: function (d) { G.ir(escolas, {natureza: d.id}); }});

            var opT = {sufixo: '%', max: 100, legenda: 'de aprovação', corPorValor: G.corTaxa};
            G.barras('gAprovEscola', taxa(@json($aprovacaoEscola)), Object.assign({}, opT, {onClick: function (d) { window.location = urlEscola + d.id; }}));
            G.barras('gAprovMunicipio', taxa(@json($aprovacaoMunicipio)), Object.assign({}, opT, {onClick: function (d) { G.ir(aqui, {municipio_id: d.id}); }}));
            G.barras('gAprovNivel', taxa(@json($aprovacaoNivel)), Object.assign({}, opT, {onClick: function (d) { G.ir(aqui, {nivel_id: d.id}); }}));
            G.barras('gAprovTipologia', taxa(@json($aprovacaoTipologia)), Object.assign({}, opT, {onClick: function (d) { G.ir(aqui, {tipologia_id: d.id}); }}));

            var anos = @json($comparacao);
            G.series('gAnos', anos.map(function (a) { return a.ano + (a.estado === 'em_curso' ? ' (em curso)' : ''); }), [
                {rotulo: 'I Trimestre', valores: anos.map(function (a) { return a.t1; }), cor: '#6E9BC5'},
                {rotulo: 'II Trimestre', valores: anos.map(function (a) { return a.t2; }), cor: '#1D6FB8'},
                {rotulo: 'III Trimestre', valores: anos.map(function (a) { return a.t3; }), cor: '#14406B'},
                {rotulo: 'Aprovação final', valores: anos.map(function (a) { return a.aprovacao; }), cor: G.VERDE}
            ], {sufixo: '%', max: 100});

            var dist = @json($distribuicao->map(fn ($g) => $g->pluck('total', 'categoria')));
            var ordem = ['Excelente', 'Bom', 'Suficiente', 'Insuficiente', 'Mau', 'Adquirido', 'Em aquisição', 'Não adquirido'];
            var cores = {'Excelente': '#1E7B4D', 'Bom': '#4F9A6E', 'Suficiente': '#6E9BC5', 'Insuficiente': '#B7791F', 'Mau': '#B42318',
                'Adquirido': '#1E7B4D', 'Em aquisição': '#B7791F', 'Não adquirido': '#B42318'};
            Object.keys(dist).forEach(function (escala, i) {
                var cats = Object.keys(dist[escala]).sort(function (a, b) { return ordem.indexOf(a) - ordem.indexOf(b); });
                G.circular('gDist' + i, cats.map(function (c) { return {nome: c, total: dist[escala][c]}; }), {cores: cats.map(function (c) { return cores[c] || '#8A9BB0'; })});
            });

            document.getElementById('pesquisaTabela').addEventListener('input', function () {
                var t = this.value.toLowerCase();
                document.querySelectorAll('#tabelaEscolas tbody tr').forEach(function (tr) { tr.style.display = tr.textContent.toLowerCase().indexOf(t) >= 0 ? '' : 'none'; });
            });
        });
    </script>
@endpush
