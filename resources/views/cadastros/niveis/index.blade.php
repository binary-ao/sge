<x-app-layout titulo="Níveis de Ensino">
    @php $gerir = auth()->user()->can('rede.catalogos'); @endphp
    <x-cabecalho titulo="Níveis de Ensino" subtitulo="Níveis que as instituições podem ministrar. Uma escola pode ministrar vários níveis, independentemente da sua tipologia."
        :migalhas="['Cadastros e Configurações' => null, 'Níveis de Ensino' => null]">
        @if ($gerir)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNivel"><i class="bi bi-plus-lg me-1"></i>Novo nível</button>
        @endif
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome ou código">
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label">Exige curso</label>
            <select name="curso" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="sim" @selected(request('curso') === 'sim')>Sim</option>
                <option value="nao" @selected(request('curso') === 'nao')>Não</option>
            </select>
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Pesquisar</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th style="width:60px">Ordem</th><th>Nível de ensino</th><th>Código</th><th class="text-end">Ciclos</th><th class="text-end">Classes</th><th class="text-end" title="Disciplinas de referência provincial">Disciplinas</th><th class="text-end" title="Escolas activas no seu âmbito">Escolas</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($niveis as $n)
                    <tr class="{{ $n->activo ? '' : 'text-secondary' }}">
                        <td>{{ $n->ordem }}</td>
                        <td>
                            <a href="{{ route('cadastros.niveis.show', $n) }}" class="fw-semibold text-decoration-none">{{ $n->nome }}</a>
                            @if ($n->exige_curso)<span class="badge badge-suave ms-1">Exige curso</span>@endif
                            @if ($n->descricao)<div class="small text-secondary">{{ \Illuminate\Support\Str::limit($n->descricao, 100) }}</div>@endif
                        </td>
                        <td><code>{{ $n->codigo }}</code></td>
                        <td class="text-end">{{ $n->ciclos_count }}</td>
                        <td class="text-end">{{ $n->classes_count }}</td>
                        <td class="text-end">{{ $n->disciplinas_count }}</td>
                        <td class="text-end">
                            @can('escolas.ver')
                                <a href="{{ route('escolas.index', ['nivel_id' => $n->id]) }}" class="fw-semibold text-decoration-none">{{ $n->escolas_count }}</a>
                            @else
                                {{ $n->escolas_count }}
                            @endcan
                        </td>
                        <td><span class="estado text-{{ $n->activo ? 'success' : 'secondary' }}">{{ $n->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="accoes">
                            <a href="{{ route('cadastros.niveis.show', $n) }}" class="btn btn-light btn-icon" title="Ver"><i class="bi bi-eye"></i></a>
                            @if ($gerir)
                                <button type="button" class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalNivel"
                                        data-accao="{{ route('cadastros.niveis.update', $n) }}"
                                        data-preencher="{{ json_encode($n->only('nome', 'codigo', 'descricao', 'ordem', 'exige_curso')) }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('cadastros.niveis.estado', $n) }}" class="d-inline"
                                      data-confirmar="{{ $n->activo ? 'Desactivar "'.$n->nome.'"? As escolas que o ministram mantêm o registo, mas deixa de estar disponível em novos cadastros.' : 'Activar "'.$n->nome.'"?' }}"
                                      data-confirmar-classe="{{ $n->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $n->activo ? 'Desactivar' : 'Activar' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $n->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $n->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="tabela-vazia"><i class="bi bi-layers"></i>Nenhum nível de ensino encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $niveis->links() }}</div>

    @if ($gerir)
        @include('cadastros.niveis._modal')
    @endif
</x-app-layout>
