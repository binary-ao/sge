<x-app-layout titulo="Professores">
    <x-cabecalho titulo="Professores" :subtitulo="'Corpo docente — '.$escolaActiva->nome"
        :migalhas="['Gestão Académica' => null, 'Professores' => null]">
        @can('professores.gerir')
            <a href="{{ route('professores.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Novo professor</a>
        @endcan
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label class="form-label">Pesquisar</label>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome, n.º de identificação ou especialidade">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Situação profissional</label>
            <select name="situacao" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach (\App\Models\Professor::SITUACOES as $v => $r)<option value="{{ $v }}" @selected(request('situacao') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Género</label>
            <select name="genero" class="form-select" data-auto-submit>
                <option value="">Todos</option><option value="M" @selected(request('genero') === 'M')>Masculino</option><option value="F" @selected(request('genero') === 'F')>Feminino</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="activo" @selected(request('estado', 'activo') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                <option value="todos" @selected(request('estado') === 'todos')>Todos</option>
            </select>
        </div>
        <div class="col-lg-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Professor</th><th>N.º identificação</th><th>Especialidade</th><th>Habilitações</th><th>Situação</th><th class="text-end">Atribuições</th><th>Conta</th><th></th></tr></thead>
                <tbody>
                @forelse ($professores as $p)
                    <tr class="{{ $p->activo ? '' : 'text-secondary' }}">
                        <td><a href="{{ route('professores.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->nome }}</a>
                            <div class="small text-secondary">{{ $p->genero === 'F' ? 'Feminino' : 'Masculino' }}{{ $p->activo ? '' : ' · Inactivo' }}</div></td>
                        <td class="small"><code>{{ $p->numero_identificacao }}</code></td>
                        <td class="small" style="max-width:240px">{{ $p->especialidade ?? '—' }}</td>
                        <td class="small">{{ $p->habilitacoes_label }}</td>
                        <td class="small">{{ $p->situacao_label }}</td>
                        <td class="text-end">{{ $p->total_atribuicoes }}</td>
                        <td>@if ($p->utilizador)<i class="bi bi-person-check text-success" title="Conta de acesso: {{ $p->utilizador->email }}"></i>@else<span class="text-secondary">—</span>@endif</td>
                        <td class="accoes">
                            <a href="{{ route('professores.show', $p) }}" class="btn btn-light btn-icon" title="Ver ficha"><i class="bi bi-eye"></i></a>
                            @can('professores.gerir')<a href="{{ route('professores.edit', $p) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-person-badge"></i>Nenhum professor encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $professores->links() }}</div>
</x-app-layout>
