<x-app-layout titulo="Início">
    <x-cabecalho titulo="Olá, {{ \Illuminate\Support\Str::of(auth()->user()->nome)->explode(' ')->first() }}"
        subtitulo="{{ auth()->user()->perfil->nome }} · {{ auth()->user()->ambito_descricao }}">
        @can('painel.provincial')
            <a href="{{ route('paineis.provincial') }}" class="btn btn-primary"><i class="bi bi-bar-chart-line me-1"></i>Painel Provincial</a>
        @elsecan('painel.municipal')
            <a href="{{ route('paineis.municipal') }}" class="btn btn-primary"><i class="bi bi-pie-chart me-1"></i>Painel Municipal</a>
        @endcan
        @if ($escolaActiva && auth()->user()->can('painel.escola'))
            <a href="{{ route('paineis.escola') }}" class="btn btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i>Painel da Escola</a>
        @endif
        @can('escolas.ver')
            <a href="{{ route('escolas.index') }}" class="btn btn-light"><i class="bi bi-building me-1"></i>Consultar escolas</a>
        @endcan
        @can('escolas.gerir')
            <a href="{{ route('escolas.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova escola</a>
        @endcan
    </x-cabecalho>

    @if ($escolaActiva)
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                <div class="cartao-estatistico border-0 p-0 flex-grow-1">
                    <div class="icone tom-azul"><i class="bi bi-building"></i></div>
                    <div>
                        <div class="small text-secondary">Escola activa</div>
                        <div class="fw-semibold fs-6">{{ $escolaActiva->nome }}</div>
                        <div class="small text-secondary">{{ $escolaActiva->tipologia->nome }} · {{ $escolaActiva->municipio->nome }} · {{ $escolaActiva->natureza_label }}</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($escolaActiva->niveisEnsino as $nivel)
                        <span class="badge badge-suave">{{ $nivel->nome }}</span>
                    @endforeach
                </div>
                <a href="{{ route('escolas.show', $escolaActiva) }}" class="btn btn-outline-primary btn-sm">Ficha da escola</a>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <x-cartao-estatistico rotulo="Municípios" :valor="$totalMunicipios" icone="map" tom="azul"
                :href="auth()->user()->can('municipios.ver') ? route('municipios.index') : null" />
        </div>
        <div class="col-6 col-xl-3">
            <x-cartao-estatistico rotulo="Escolas" :valor="$totalEscolas" icone="building" tom="info"
                :href="auth()->user()->can('escolas.ver') ? route('escolas.index') : null" />
        </div>
        <div class="col-6 col-xl-3">
            <x-cartao-estatistico rotulo="Em funcionamento" :valor="$emFuncionamento" icone="check2-circle" tom="sucesso"
                :href="auth()->user()->can('escolas.ver') ? route('escolas.index', ['estado' => 'em_funcionamento']) : null" />
        </div>
        <div class="col-6 col-xl-3">
            <x-cartao-estatistico rotulo="Níveis de ensino ministrados" :valor="$totalNiveis" icone="layers" tom="alerta" />
        </div>
        <div class="col-6 col-xl-4">
            <x-cartao-estatistico rotulo="Alunos activos" :valor="number_format($totalAlunos, 0, ',', ' ')" icone="people" tom="azul" />
        </div>
        <div class="col-6 col-xl-4">
            <x-cartao-estatistico rotulo="Professores" :valor="number_format($totalProfessores, 0, ',', ' ')" icone="person-badge" tom="info" />
        </div>
        <div class="col-12 col-xl-4">
            <x-cartao-estatistico rotulo="Turmas em {{ $ano?->designacao }}" :valor="$totalTurmas" icone="grid-3x3-gap" tom="sucesso" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><h2 class="titulo">Escolas por tipologia institucional</h2>
                    <span class="small text-secondary d-none d-md-inline">Clique numa barra para ver as escolas</span></div>
                <div class="card-body">
                    @if ($porTipologia->isEmpty())
                        <p class="text-secondary text-center my-5">Sem escolas registadas.</p>
                    @else
                        <div style="height: {{ max(220, $porTipologia->count() * 42) }}px"><canvas id="graficoTipologia"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><h2 class="titulo">Instituições por natureza</h2></div>
                <div class="card-body">
                    @if ($porNatureza->isEmpty())
                        <p class="text-secondary text-center my-5">Sem dados.</p>
                    @else
                        <div style="height: 240px"><canvas id="graficoNatureza"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h2 class="titulo">Escolas por município</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Município</th><th class="text-end">Escolas</th></tr></thead>
                        <tbody>
                        @forelse ($porMunicipio as $m)
                            <tr>
                                <td>
                                    @can('escolas.ver')
                                        <a href="{{ route('escolas.index', ['municipio_id' => $m->id]) }}">{{ $m->nome }}</a>
                                    @else
                                        {{ $m->nome }}
                                    @endcan
                                </td>
                                <td class="text-end fw-semibold">{{ $m->escolas_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="tabela-vazia">Sem municípios no seu âmbito.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h2 class="titulo">Escolas por nível de ensino ministrado</h2></div>
                <div class="card-body">
                    <p class="small text-secondary">Uma escola pode ministrar vários níveis; por isso, a soma pode exceder o total de escolas.</p>
                    @forelse ($porNivel as $n)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                @can('escolas.ver')
                                    <a href="{{ route('escolas.index', ['nivel_id' => $n['id']]) }}" class="text-decoration-none">{{ $n['nome'] }}</a>
                                @else
                                    <span>{{ $n['nome'] }}</span>
                                @endcan
                                <strong>{{ $n['total'] }}</strong>
                            </div>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar bg-primary" style="width: {{ $totalEscolas ? round($n['total'] / $totalEscolas * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary text-center my-4">Sem dados.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small mt-4 mb-0">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        Os valores acima são calculados em tempo real a partir da base de dados. Os indicadores académicos, os filtros globais e as comparações
        estão nos painéis provincial, municipal e da escola.
    </div>

    @push('scripts')
        <script src="{{ asset('libs/chartjs/chart.umd.min.js') }}"></script>
        <script>
            (function () {
                Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
                Chart.defaults.color = '#5B6779';
                var urlEscolas = @json(auth()->user()->can('escolas.ver') ? route('escolas.index') : null);
                var tip = @json($porTipologia->map(fn ($t) => ['id' => $t->id, 'nome' => $t->nome, 'total' => (int) $t->total]));
                var el = document.getElementById('graficoTipologia');
                if (el) {
                    new Chart(el, {
                        type: 'bar',
                        data: { labels: tip.map(function (t) { return t.nome; }), datasets: [{ data: tip.map(function (t) { return t.total; }), backgroundColor: '#14406B', borderRadius: 4, maxBarThickness: 26 }] },
                        options: {
                            indexAxis: 'y', maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return c.raw + ' escola(s)'; } } } },
                            scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#EEF1F5' } }, y: { grid: { display: false } } },
                            onClick: function (e, itens) { if (itens.length && urlEscolas) { window.location = urlEscolas + '?tipologia_id=' + tip[itens[0].index].id; } },
                            onHover: function (e, itens) { e.native.target.style.cursor = itens.length && urlEscolas ? 'pointer' : 'default'; }
                        }
                    });
                }
                var nat = @json($porNatureza);
                var rotulos = @json(\App\Models\Escola::NATUREZAS);
                var el2 = document.getElementById('graficoNatureza');
                if (el2) {
                    var chaves = Object.keys(nat);
                    new Chart(el2, {
                        type: 'doughnut',
                        data: { labels: chaves.map(function (k) { return rotulos[k]; }), datasets: [{ data: chaves.map(function (k) { return nat[k]; }), backgroundColor: ['#0B2A4A', '#1D6FB8', '#9DB4CC'], borderWidth: 2, borderColor: '#fff' }] },
                        options: {
                            maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                            onClick: function (e, itens) { if (itens.length && urlEscolas) { window.location = urlEscolas + '?natureza=' + chaves[itens[0].index]; } }
                        }
                    });
                }
            })();
        </script>
    @endpush
</x-app-layout>
