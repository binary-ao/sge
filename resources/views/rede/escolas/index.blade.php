<x-app-layout titulo="Escolas">
    <x-cabecalho titulo="Escolas" subtitulo="Instituições de ensino da rede escolar no seu âmbito"
        :migalhas="['Cadastros e Configurações' => null, 'Escolas' => null]">
        @can('rede.catalogos')
            <a href="{{ route('tipologias.index') }}" class="btn btn-light"><i class="bi bi-diagram-3 me-1"></i>Tipologias</a>
        @endcan
        @can('escolas.gerir')
            <a href="{{ route('escolas.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova escola</a>
        @endcan
    </x-cabecalho>

    <form method="GET" class="barra-filtros">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label" for="q">Pesquisar</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome, código ou comuna">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Município</label>
                <select name="municipio_id" class="form-select" data-auto-submit>
                    <option value="">Todos</option>
                    @foreach ($municipios as $m)
                        <option value="{{ $m->id }}" @selected(request('municipio_id') == $m->id)>{{ $m->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Tipologia</label>
                <select name="tipologia_id" class="form-select" data-auto-submit>
                    <option value="">Todas</option>
                    @foreach ($tipologias as $t)
                        <option value="{{ $t->id }}" @selected(request('tipologia_id') == $t->id)>{{ $t->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Nível de ensino</label>
                <select name="nivel_id" class="form-select" data-auto-submit>
                    <option value="">Todos</option>
                    @foreach ($niveis as $n)
                        <option value="{{ $n->id }}" @selected(request('nivel_id') == $n->id)>{{ $n->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-4">
                <label class="form-label">Natureza</label>
                <select name="natureza" class="form-select" data-auto-submit>
                    <option value="">Todas</option>
                    @foreach (\App\Models\Escola::NATUREZAS as $v => $r)
                        <option value="{{ $v }}" @selected(request('natureza') === $v)>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Funcionamento</label>
                <select name="estado" class="form-select" data-auto-submit>
                    <option value="">Todos</option>
                    @foreach (\App\Models\Escola::ESTADOS as $v => $r)
                        <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Registo</label>
                <select name="registo" class="form-select" data-auto-submit>
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('registo') === 'activo')>Activas</option>
                    <option value="inactivo" @selected(request('registo') === 'inactivo')>Desactivadas</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Aplicar</button>
            @if (request()->hasAny(['q', 'municipio_id', 'tipologia_id', 'nivel_id', 'natureza', 'estado', 'registo']))
                <a href="{{ route('escolas.index') }}" class="btn btn-light btn-sm">Limpar filtros</a>
            @endif
            <span class="ms-auto small text-secondary align-self-center">{{ $escolas->total() }} {{ $escolas->total() === 1 ? 'instituição' : 'instituições' }}</span>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Escola</th><th>Município</th><th>Tipologia</th><th>Níveis ministrados</th><th>Natureza</th>
                    <th class="text-end">Alunos</th><th class="text-end">Professores</th><th>Funcionamento</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($escolas as $e)
                    <tr class="{{ $e->activo ? '' : 'text-secondary' }}">
                        <td>
                            <a href="{{ route('escolas.show', $e) }}" class="fw-semibold text-decoration-none">{{ $e->nome }}</a>
                            @unless ($e->activo)<span class="badge text-bg-secondary ms-1">Desactivada</span>@endunless
                            <div class="small text-secondary">{{ $e->codigo }}</div>
                        </td>
                        <td>{{ $e->municipio->nome }}</td>
                        <td class="small">{{ $e->tipologia->nome }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1" style="max-width:280px">
                                @foreach ($e->niveisEnsino as $n)
                                    <span class="badge badge-suave">{{ $n->nome }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="small">{{ $e->natureza_label }}</td>
                        <td class="text-end fw-semibold">{{ number_format($e->total_alunos, 0, ',', ' ') }}</td>
                        <td class="text-end">{{ $e->total_professores }}</td>
                        <td><span class="estado text-{{ $e->estado_cor }}">{{ $e->estado_label }}</span></td>
                        <td class="accoes">
                            <a href="{{ route('escolas.show', $e) }}" class="btn btn-light btn-icon" title="Ver detalhes"><i class="bi bi-eye"></i></a>
                            @can('escolas.gerir')
                                <a href="{{ route('escolas.edit', $e) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('escolas.estado', $e) }}" class="d-inline"
                                      data-confirmar="{{ $e->activo ? 'Desactivar '.$e->nome.'? O histórico mantém-se, mas a escola deixa de contar nos indicadores e de estar disponível para operações.' : 'Activar '.$e->nome.'?' }}"
                                      data-confirmar-classe="{{ $e->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $e->activo ? 'Desactivar' : 'Activar' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $e->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $e->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="tabela-vazia"><i class="bi bi-building"></i>Nenhuma escola corresponde aos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $escolas->links() }}</div>

    <p class="small text-secondary mt-2"><i class="bi bi-info-circle"></i> Alunos com situação "Activo" e professores activos, calculados a partir dos registos das escolas.</p>
</x-app-layout>
