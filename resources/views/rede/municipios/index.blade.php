<x-app-layout titulo="Municípios">
    <x-cabecalho titulo="Municípios" subtitulo="Municípios da província, com escolas e alunos activos" :migalhas="['Cadastros e Configurações' => null, 'Municípios' => null]">
        @can('municipios.gerir')
            <a href="{{ route('municipios.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo município</a>
        @endcan
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-{{ $provincias->count() > 1 ? 4 : 6 }}">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome, código ou sede">
        </div>
        @if ($provincias->count() > 1)
            <div class="col-md-2">
                <label class="form-label">Província</label>
                <select name="provincia_id" class="form-select" data-auto-submit>
                    <option value="">Todas</option>
                    @foreach ($provincias as $p)<option value="{{ $p->id }}" @selected(request('provincia_id') == $p->id)>{{ $p->nome }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="col-md-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Pesquisar</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Município</th><th>Código</th><th>Província</th><th>Sede</th><th class="text-end" title="Escolas activas (total registado)">Escolas</th><th class="text-end" title="Alunos activos nas escolas activas">Alunos</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($municipios as $m)
                    <tr>
                        <td><a href="{{ route('municipios.show', $m) }}" class="fw-semibold text-decoration-none">{{ $m->nome }}</a></td>
                        <td><code>{{ $m->codigo }}</code></td>
                        <td class="small">{{ $m->provincia?->nome }}</td>
                        <td>{{ $m->sede ?? '—' }}</td>
                        <td class="text-end">
                            <span class="fw-semibold">{{ $m->escolas_activas }}</span>
                            @if ($m->escolas_count !== $m->escolas_activas)<span class="small text-secondary">({{ $m->escolas_count }})</span>@endif
                        </td>
                        <td class="text-end">{{ number_format($m->total_alunos, 0, ',', ' ') }}</td>
                        <td>
                            <span class="estado text-{{ $m->activo ? 'success' : 'secondary' }}">{{ $m->activo ? 'Activo' : 'Inactivo' }}</span>
                        </td>
                        <td class="accoes">
                            <a href="{{ route('municipios.show', $m) }}" class="btn btn-light btn-icon" title="Ver"><i class="bi bi-eye"></i></a>
                            @can('municipios.gerir')
                                <a href="{{ route('municipios.edit', $m) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('municipios.estado', $m) }}" class="d-inline"
                                      data-confirmar="{{ $m->activo ? 'Desactivar o município '.$m->nome.'? Deixará de estar disponível para novos registos (só é possível se não tiver escolas activas).' : 'Activar o município '.$m->nome.'?' }}"
                                      data-confirmar-classe="{{ $m->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $m->activo ? 'Desactivar' : 'Activar' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $m->activo ? 'Desactivar' : 'Activar' }}">
                                        <i class="bi bi-{{ $m->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-map"></i>Nenhum município encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $municipios->links() }}</div>
</x-app-layout>
