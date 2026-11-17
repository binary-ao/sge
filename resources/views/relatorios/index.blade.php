<x-app-layout titulo="Relatórios">
    <x-cabecalho titulo="Relatórios" subtitulo="Pré-visualização e exportação em PDF e Excel, sempre limitadas ao seu âmbito de acesso"
        :migalhas="['Relatórios' => null]" />

    <div class="row g-3">
        @foreach ($relatorios as $grupo => $lista)
            <div class="col-xl-4 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h2 class="titulo">Relatórios {{ mb_strtolower($grupo) }}</h2>
                        @if ($grupo === 'Escolares')<span class="small text-secondary">{{ $escolaActiva?->nome ?? 'Exige escola activa' }}</span>@endif</div>
                    <div class="list-group list-group-flush">
                        @foreach ($lista as $r)
                            <a href="{{ route('relatorios.show', $r['chave']) }}" class="list-group-item list-group-item-action {{ $r['disponivel'] ? '' : 'text-secondary' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold small">{{ $r['titulo'] }}</span>
                                    @if (! $r['disponivel'])<span class="badge text-bg-light border">Fase 6</span>@else<i class="bi bi-chevron-right small"></i>@endif
                                </div>
                                <div class="small text-secondary">{{ $r['descricao'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
