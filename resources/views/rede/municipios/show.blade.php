<x-app-layout :titulo="$municipio->nome">
    <x-cabecalho :titulo="'Município: '.$municipio->nome" subtitulo="{{ $municipio->codigo }} · {{ $municipio->provincia?->nome }} · Sede: {{ $municipio->sede ?? '—' }}"
        :migalhas="['Cadastros e Configurações' => null, 'Municípios' => route('municipios.index'), $municipio->nome => null]">
        @can('municipios.gerir')
            <a href="{{ route('municipios.edit', $municipio) }}" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Editar</a>
            <form method="POST" action="{{ route('municipios.estado', $municipio) }}"
                  data-confirmar="{{ $municipio->activo ? 'Desactivar o município? Só é possível se não tiver escolas activas.' : 'Activar o município?' }}"
                  data-confirmar-classe="{{ $municipio->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $municipio->activo ? 'Desactivar' : 'Activar' }}">
                @csrf @method('PATCH')
                <button class="btn {{ $municipio->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $municipio->activo ? 'Desactivar' : 'Activar' }}</button>
            </form>
        @endcan
        @can('escolas.gerir')
            @if ($municipio->activo)
                <a href="{{ route('escolas.create', ['municipio_id' => $municipio->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova escola</a>
            @endif
        @endcan
    </x-cabecalho>

    @unless ($municipio->activo)
        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Este município está desactivado.</div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-cartao-estatistico rotulo="Escolas activas" :valor="$indicadores['escolas']" icone="building"
            :detalhe="$indicadores['inactivas'] ? $indicadores['inactivas'].' desactivada(s)' : null" /></div>
        <div class="col-6 col-lg-3"><x-cartao-estatistico rotulo="Alunos activos" :valor="number_format($indicadores['alunos'], 0, ',', ' ')" icone="people" tom="info" /></div>
        <div class="col-6 col-lg-3"><x-cartao-estatistico rotulo="Professores activos" :valor="$indicadores['professores']" icone="person-badge" tom="sucesso" /></div>
        <div class="col-6 col-lg-3"><x-cartao-estatistico rotulo="Turmas (ano actual)" :valor="$indicadores['turmas']" icone="grid-3x3-gap" tom="alerta" /></div>
    </div>
    <div class="d-flex flex-wrap gap-2 small mb-3">
        @foreach (\App\Models\Escola::NATUREZAS as $v => $r)
            <span class="badge text-bg-light border fw-normal">{{ $r }}: <strong>{{ $escolas->where('activo', true)->where('natureza', $v)->count() }}</strong></span>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h2 class="titulo">Escolas do município</h2>
            @can('escolas.ver')<a href="{{ route('escolas.index', ['municipio_id' => $municipio->id]) }}" class="small">Ver com filtros</a>@endcan</div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Escola</th><th>Tipologia</th><th>Níveis</th><th>Natureza</th><th class="text-end">Alunos</th><th>Funcionamento</th><th>Registo</th></tr></thead>
                <tbody>
                @forelse ($escolas as $e)
                    <tr class="{{ $e->activo ? '' : 'text-secondary' }}">
                        <td><a href="{{ route('escolas.show', $e) }}" class="fw-semibold text-decoration-none">{{ $e->nome }}</a></td>
                        <td class="small">{{ $e->tipologia->nome }}</td>
                        <td>@foreach ($e->niveisEnsino as $n)<span class="badge badge-suave me-1">{{ $n->nome }}</span>@endforeach</td>
                        <td class="small">{{ $e->natureza_label }}</td>
                        <td class="text-end">{{ $e->total_alunos }}</td>
                        <td><span class="estado text-{{ $e->estado_cor }}">{{ $e->estado_label }}</span></td>
                        <td>@if ($e->activo)<span class="badge text-bg-light border">Activo</span>@else<span class="badge text-bg-secondary">Desactivado</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-building"></i>Sem escolas registadas neste município.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
