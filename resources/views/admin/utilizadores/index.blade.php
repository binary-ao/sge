<x-app-layout titulo="Utilizadores">
    <x-cabecalho titulo="Utilizadores" subtitulo="Contas de acesso, perfis e atribuição a municípios e escolas"
        :migalhas="['Sistema' => null, 'Utilizadores' => null]">
        <a href="{{ route('utilizadores.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Novo utilizador</a>
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-4 col-md-6">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome ou e-mail">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Perfil</label>
            <select name="perfil_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($perfis as $p)
                    <option value="{{ $p->id }}" @selected(request('perfil_id') == $p->id)>{{ $p->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Escola</label>
            <select name="escola_id" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($escolas as $e)
                    <option value="{{ $e->id }}" @selected(request('escola_id') == $e->id)>{{ $e->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
            </select>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Utilizador</th><th>Perfil</th><th>Âmbito</th><th>Último acesso</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($utilizadores as $u)
                    <tr class="{{ $u->activo ? '' : 'text-secondary' }}">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar">{{ $u->iniciais }}</span>
                                <div><div class="fw-semibold">{{ $u->nome }}</div><div class="small text-secondary">{{ $u->email }}</div></div>
                            </div>
                        </td>
                        <td><span class="badge badge-suave">{{ $u->perfil->nome }}</span></td>
                        <td class="small" style="max-width:280px">{{ $u->ambito_descricao }}</td>
                        <td class="small">{{ $u->ultimo_acesso_em?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                        <td><span class="estado text-{{ $u->activo ? 'success' : 'secondary' }}">{{ $u->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="accoes">
                            <a href="{{ route('utilizadores.edit', $u) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ route('utilizadores.estado', $u) }}" class="d-inline"
                                      data-confirmar="{{ $u->activo ? 'Desactivar a conta de '.$u->nome.'? As sessões abertas serão terminadas.' : 'Reactivar a conta de '.$u->nome.'?' }}"
                                      data-confirmar-classe="{{ $u->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                        <i class="bi bi-{{ $u->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i>
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabela-vazia"><i class="bi bi-people"></i>Nenhum utilizador encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $utilizadores->links() }}</div>
</x-app-layout>
