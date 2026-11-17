@php $campo = $cfg['campo_codigo']; @endphp
<x-app-layout :titulo="$cfg['titulo']">
    <x-cabecalho :titulo="$cfg['titulo']" :subtitulo="$cfg['descricao']"
        :migalhas="['Cadastros e Configurações' => null, 'Escolas' => route('escolas.index'), $cfg['titulo'] => null]">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalItem" data-item="">
            <i class="bi bi-plus-lg me-1"></i>Adicionar
        </button>
    </x-cabecalho>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-9">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome">
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Pesquisar</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th style="width:60px">Ordem</th><th>Nome</th><th>{{ $cfg['rotulo_codigo'] }}</th><th class="text-end">Escolas</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($itens as $item)
                    <tr class="{{ $item->activo ? '' : 'text-secondary' }}">
                        <td>{{ $item->ordem }}</td>
                        <td>
                            <div class="fw-semibold">{{ $item->nome }}</div>
                            @if ($item->descricao)<div class="small text-secondary">{{ \Illuminate\Support\Str::limit($item->descricao, 110) }}</div>@endif
                        </td>
                        <td><code>{{ $item->$campo ?? '—' }}</code></td>
                        <td class="text-end">
                            @php $filtro = $tipo === 'tipologias' ? ['tipologia_id' => $item->id] : ['nivel_id' => $item->id]; @endphp
                            <a href="{{ route('escolas.index', $filtro) }}" class="fw-semibold text-decoration-none">{{ $item->escolas_count }}</a>
                        </td>
                        <td><span class="estado text-{{ $item->activo ? 'success' : 'secondary' }}">{{ $item->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="accoes">
                            <button type="button" class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalItem"
                                    data-item="{{ json_encode(['id' => $item->id, 'nome' => $item->nome, 'codigo' => $item->$campo, 'descricao' => $item->descricao, 'ordem' => $item->ordem]) }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route($tipo.'.estado', $item->id) }}" class="d-inline"
                                  data-confirmar="{{ $item->activo ? 'Desactivar "'.$item->nome.'"? As escolas já classificadas mantêm a classificação, mas deixará de estar disponível em novos registos.' : 'Activar "'.$item->nome.'"?' }}"
                                  data-confirmar-classe="{{ $item->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $item->activo ? 'Desactivar' : 'Activar' }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-light btn-icon" title="{{ $item->activo ? 'Desactivar' : 'Activar' }}">
                                    <i class="bi bi-{{ $item->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabela-vazia"><i class="bi bi-inbox"></i>Sem registos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de criação/edição --}}
    <div class="modal fade" id="modalItem" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" id="formItem" action="{{ route($tipo.'.store') }}" novalidate>
                @csrf
                <input type="hidden" name="_method" value="POST" id="metodoItem">
                <input type="hidden" name="_item_id" value="{{ old('_item_id') }}" id="campoId">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-semibold" id="tituloModal">Adicionar {{ $cfg['singular'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label obrigatorio" for="campoNome">Nome</label>
                        <input id="campoNome" name="nome" maxlength="120" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                        <x-campo-erro campo="nome" />
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label {{ $campo === 'codigo' ? 'obrigatorio' : '' }}" for="campoCodigo">{{ $cfg['rotulo_codigo'] }}</label>
                            <input id="campoCodigo" name="{{ $campo }}" maxlength="30" class="form-control text-uppercase @error($campo) is-invalid @enderror" value="{{ old($campo) }}">
                            <x-campo-erro :campo="$campo" />
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="campoOrdem">Ordem</label>
                            <input type="number" id="campoOrdem" name="ordem" min="0" max="999" class="form-control" value="{{ old('ordem', 0) }}">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="campoDescricao">Descrição</label>
                        <textarea id="campoDescricao" name="descricao" rows="3" maxlength="1000" class="form-control">{{ old('descricao') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var modal = document.getElementById('modalItem');
                var form = document.getElementById('formItem');
                var urlCriar = @json(route($tipo.'.store'));
                var urlEditar = @json(route($tipo.'.update', ['id' => '__ID__']));
                var singular = @json($cfg['singular']);
                modal.addEventListener('show.bs.modal', function (ev) {
                    if (!ev.relatedTarget) { return; } // reabertura após erro: manter valores submetidos
                    var dados = ev.relatedTarget.dataset.item ? JSON.parse(ev.relatedTarget.dataset.item) : null;
                    document.getElementById('campoId').value = dados ? dados.id : '';
                    form.action = dados ? urlEditar.replace('__ID__', dados.id) : urlCriar;
                    document.getElementById('metodoItem').value = dados ? 'PUT' : 'POST';
                    document.getElementById('tituloModal').textContent = (dados ? 'Editar ' : 'Adicionar ') + singular;
                    document.getElementById('campoNome').value = dados ? dados.nome : '';
                    document.getElementById('campoCodigo').value = dados && dados.codigo ? dados.codigo : '';
                    document.getElementById('campoOrdem').value = dados ? dados.ordem : 0;
                    document.getElementById('campoDescricao').value = dados && dados.descricao ? dados.descricao : '';
                    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
                });
                @if ($errors->any())
                    // Reabrir o modal com os erros de validação.
                    document.addEventListener('DOMContentLoaded', function () {
                        var idAnterior = document.getElementById('campoId').value;
                        if (idAnterior) {
                            form.action = urlEditar.replace('__ID__', idAnterior);
                            document.getElementById('metodoItem').value = 'PUT';
                            document.getElementById('tituloModal').textContent = 'Editar ' + singular;
                        }
                        new bootstrap.Modal(modal).show();
                    });
                @endif
            })();
        </script>
    @endpush
</x-app-layout>
