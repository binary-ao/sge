<x-app-layout titulo="Classes e Ciclos">
    @php $global = auth()->user()->can('academico.global'); @endphp
    <x-cabecalho titulo="Classes e Ciclos" subtitulo="Estrutura de ensino de referência: classes por nível e ciclo, regime de avaliação e disciplinas aplicáveis"
        :migalhas="['Cadastros e Configurações' => null, 'Classes e Ciclos' => null]">
        @if ($global)
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalCiclo"><i class="bi bi-plus-lg me-1"></i>Novo ciclo</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalClasse"><i class="bi bi-plus-lg me-1"></i>Nova classe</button>
        @endif
    </x-cabecalho>

    @if ($restrito)
        <div class="alert alert-light border small py-2"><i class="bi bi-person-badge me-1"></i>Estão listadas apenas as classes das turmas que lhe estão atribuídas.</div>
    @endif

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-2 col-md-6">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome da classe">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Nível de ensino</label>
            <select name="nivel_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($niveis as $n)<option value="{{ $n->id }}" @selected(request('nivel_id') == $n->id)>{{ $n->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Ciclo</label>
            <select name="ciclo_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($ciclos as $c)<option value="{{ $c->id }}" @selected(request('ciclo_id') == $c->id)>{{ $c->nome }} ({{ $c->nivelEnsino->nome }})</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Regime</label>
            <select name="regime" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach (\App\Models\Classe::REGIMES as $v => $r)<option value="{{ $v }}" @selected(request('regime') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg col-md-2 col-6">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
            </select>
        </div>
        <div class="col-lg-auto col-md-2 col-6"><button class="btn btn-primary w-100" title="Pesquisar"><i class="bi bi-search"></i></button></div>
    </form>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Classe</th><th>Nível</th><th>Ciclo</th><th>Regime</th><th class="text-end" title="Disciplinas do plano curricular aplicável">Disciplinas</th><th class="text-end" title="Turmas no ano lectivo actual (no seu âmbito)">Turmas</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($classes as $classe)
                            @php $plano = $planos[$classe->id] ?? null; @endphp
                            <tr class="{{ $classe->activo ? '' : 'text-secondary' }}">
                                <td><a href="{{ route('cadastros.classes.show', $classe) }}" class="fw-semibold text-decoration-none">{{ $classe->nome }}</a></td>
                                <td class="small">{{ $classe->nivelEnsino->nome }}</td>
                                <td class="small">{{ $classe->ciclo?->nome ?? '—' }}</td>
                                <td><span class="badge {{ $classe->regime === 'exame_nacional' ? 'text-bg-warning' : 'badge-suave' }}">{{ $classe->regime_label }}</span></td>
                                <td class="text-end">
                                    @if ($plano){{ $plano->disciplinas_count }}@else<span class="text-warning" title="Sem plano curricular"><i class="bi bi-exclamation-triangle"></i></span>@endif
                                </td>
                                <td class="text-end">{{ $classe->turmas_ano }}</td>
                                <td><span class="estado text-{{ $classe->activo ? 'success' : 'secondary' }}">{{ $classe->activo ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="accoes">
                                    <a href="{{ route('cadastros.classes.show', $classe) }}" class="btn btn-light btn-icon" title="Ver e configurar disciplinas"><i class="bi bi-eye"></i></a>
                                    @if ($global)
                                        <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalClasse"
                                                data-accao="{{ route('cadastros.classes.update', $classe) }}"
                                                data-preencher="{{ json_encode($classe->only('nivel_ensino_id', 'ciclo_id', 'nome', 'numero', 'regime', 'ordem')) }}"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="{{ route('cadastros.classes.estado', $classe) }}" class="d-inline"
                                              data-confirmar="{{ $classe->activo ? 'Desactivar a '.$classe->nome_completo.'? O histórico de turmas e notas mantém-se.' : 'Activar a '.$classe->nome_completo.'?' }}"
                                              data-confirmar-classe="{{ $classe->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $classe->activo ? 'Desactivar' : 'Activar' }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-light btn-icon" title="{{ $classe->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $classe->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-diagram-2"></i>Nenhuma classe encontrada.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $classes->links() }}</div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h2 class="titulo">Ciclos</h2>
                    @if ($global)<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalCiclo" title="Novo ciclo"><i class="bi bi-plus-lg"></i></button>@endif
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($ciclos->groupBy('nivel_ensino_id') as $doNivel)
                        <li class="list-group-item bg-light small fw-semibold text-secondary py-1">{{ $doNivel->first()->nivelEnsino->nome }}</li>
                        @foreach ($doNivel as $c)
                            <li class="list-group-item d-flex justify-content-between align-items-center {{ $c->activo ? '' : 'text-secondary' }}">
                                <span>
                                    <a href="{{ route('cadastros.classes.index', ['ciclo_id' => $c->id]) }}" class="text-decoration-none">{{ $c->nome }}</a>
                                    <span class="small text-secondary">· {{ $c->classes_count }} {{ $c->classes_count === 1 ? 'classe' : 'classes' }}</span>
                                    @unless ($c->activo)<span class="badge text-bg-secondary ms-1">Inactivo</span>@endunless
                                </span>
                                @if ($global)
                                    <span class="text-nowrap">
                                        <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalCiclo"
                                                data-accao="{{ route('cadastros.ciclos.update', $c) }}"
                                                data-preencher="{{ json_encode($c->only('nivel_ensino_id', 'nome', 'ordem')) }}"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="{{ route('cadastros.ciclos.estado', $c) }}" class="d-inline"
                                              data-confirmar="{{ $c->activo ? 'Desactivar o ciclo '.$c->nome.'?' : 'Activar o ciclo '.$c->nome.'?' }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-light btn-icon" title="{{ $c->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $c->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                        </form>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    @empty
                        <li class="list-group-item small text-secondary">Sem ciclos registados.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card mt-3">
                <div class="card-body small">
                    <div class="fw-semibold mb-2 text-primary">Regimes de avaliação</div>
                    <p class="text-secondary mb-2">O regime da classe determina as fórmulas aplicáveis no motor de avaliação. As classes de Exame Nacional não usam as fórmulas das classes de transição.</p>
                    <ul class="mb-0 ps-3">
                        @foreach (\App\Models\Classe::REGIMES as $r)<li>{{ $r }}</li>@endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if ($global)
        @include('cadastros.classes._modais')
    @endif
</x-app-layout>
