<x-app-layout titulo="Disciplinas">
    @php $podeCriar = auth()->user()->can('academico.global') || ($escolaActiva && $controlador->podeEditar($escolaActiva->id)); @endphp
    <x-cabecalho titulo="Disciplinas" subtitulo="Catálogo de disciplinas: referências provinciais e disciplinas próprias das escolas"
        :migalhas="['Cadastros e Configurações' => null, 'Disciplinas' => null]">
        @if ($podeCriar)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDisciplina"><i class="bi bi-plus-lg me-1"></i>Nova disciplina</button>
        @endif
    </x-cabecalho>

    @if ($restrito)
        <div class="alert alert-light border small py-2"><i class="bi bi-person-badge me-1"></i>Estão listadas apenas as disciplinas que lecciona na escola activa.</div>
    @elseif (! $escolaActiva)
        <div class="alert alert-light border small py-2"><i class="bi bi-globe2 me-1"></i>Sem escola activa: estão listadas as disciplinas de referência provincial.</div>
    @endif

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-2 col-md-6">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome ou código">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Nível de ensino</label>
            <select name="nivel_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($niveis as $n)<option value="{{ $n->id }}" @selected(request('nivel_id') == $n->id)>{{ $n->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($classes as $c)<option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nome_completo }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Componente</label>
            <select name="componente" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach (\App\Models\Disciplina::COMPONENTES as $v => $r)<option value="{{ $v }}" @selected(request('componente') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg col-md-3 col-6">
            <label class="form-label">Âmbito</label>
            <select name="ambito" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="referencia" @selected(request('ambito') === 'referencia')>Referência</option>
                <option value="escola" @selected(request('ambito') === 'escola')>Escola</option>
            </select>
        </div>
        <div class="col-lg col-md-3 col-6">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
            </select>
        </div>
        <div class="col-lg-auto col-md-12"><button class="btn btn-primary w-100" title="Pesquisar"><i class="bi bi-search"></i></button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Disciplina</th><th>Código</th><th>Níveis de ensino</th><th>Componente</th><th class="text-end" title="Planos curriculares (classes) que incluem a disciplina">Classes</th><th>Âmbito</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($disciplinas as $d)
                    <tr class="{{ $d->activo ? '' : 'text-secondary' }}">
                        <td>
                            <a href="{{ route('cadastros.disciplinas.show', $d->id) }}" class="fw-semibold text-decoration-none">{{ $d->nome }}</a>
                            @if ($d->nuclear)<span class="badge text-bg-warning ms-1" title="Restrições especiais nas regras de transição">Nuclear</span>@endif
                        </td>
                        <td><code>{{ $d->sigla }}</code></td>
                        <td class="small">
                            @forelse ($d->niveisEnsino as $n)<span class="badge text-bg-light border me-1">{{ $n->codigo }}</span>@empty<span class="text-warning" title="Sem nível de ensino associado"><i class="bi bi-exclamation-triangle"></i> Por associar</span>@endforelse
                        </td>
                        <td class="small">{{ $d->componente_label }}</td>
                        <td class="text-end">{{ $d->classes_count }}</td>
                        <td>@if ($d->escola_id)<span class="badge badge-suave" title="{{ $d->escola?->nome }}">Escola</span>@else<span class="badge text-bg-light border">Referência</span>@endif</td>
                        <td><span class="estado text-{{ $d->activo ? 'success' : 'secondary' }}">{{ $d->activo ? 'Activa' : 'Inactiva' }}</span></td>
                        <td class="accoes">
                            <a href="{{ route('cadastros.disciplinas.show', $d->id) }}" class="btn btn-light btn-icon" title="Ver"><i class="bi bi-eye"></i></a>
                            @if ($controlador->podeEditar($d->escola_id))
                                <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalDisciplina"
                                        data-accao="{{ route('cadastros.disciplinas.update', $d->id) }}"
                                        data-preencher="{{ json_encode($d->only('nome', 'sigla', 'componente', 'nuclear') + ['niveis' => $d->niveisEnsino->pluck('id')]) }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('cadastros.disciplinas.estado', $d->id) }}" class="d-inline"
                                      data-confirmar="{{ $d->activo ? 'Desactivar a disciplina '.$d->nome.'? Deixa de estar disponível em novos planos; as notas e pautas existentes mantêm-se.' : 'Activar a disciplina '.$d->nome.'?' }}"
                                      data-confirmar-classe="{{ $d->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $d->activo ? 'Desactivar' : 'Activar' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $d->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $d->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                </form>
                            @else
                                <span class="btn btn-icon text-secondary" title="{{ $d->escola_id ? 'Disciplina de outra escola' : 'Referência provincial: alterada pela administração provincial' }}"><i class="bi bi-lock"></i></span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-book"></i>Nenhuma disciplina encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $disciplinas->links() }}</div>

    @if ($podeCriar)
        @include('cadastros.disciplinas._modal')
    @endif
</x-app-layout>
