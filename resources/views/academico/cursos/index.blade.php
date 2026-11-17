<x-app-layout titulo="Cursos">
    @php $podeCriar = auth()->user()->can('academico.global') || ($escolaActiva && $controlador->podeEditar($escolaActiva->id)); @endphp
    <x-cabecalho titulo="Configuração Académica" subtitulo="Cursos (II Ciclo e Ensino Técnico-Profissional)"
        :migalhas="['Gestão Académica' => null, 'Configuração Académica' => route('academico.index'), 'Cursos' => null]">
        @if ($podeCriar)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCurso"><i class="bi bi-plus-lg me-1"></i>Novo curso</button>
        @endif
    </x-cabecalho>

    @include('academico._nav')

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Nível de ensino</label>
            <select name="nivel_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($niveis->where('exige_curso', true) as $n)
                    <option value="{{ $n->id }}" @selected(request('nivel_id') == $n->id)>{{ $n->nome }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Curso</th><th>Código</th><th>Nível</th><th>Âmbito</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($cursos as $c)
                    <tr class="{{ $c->activo ? '' : 'text-secondary' }}">
                        <td class="fw-semibold">{{ $c->nome }}@if ($c->descricao)<div class="small text-secondary fw-normal">{{ \Illuminate\Support\Str::limit($c->descricao, 90) }}</div>@endif</td>
                        <td><code>{{ $c->codigo }}</code></td>
                        <td class="small">{{ $c->nivelEnsino->nome }}</td>
                        <td>@if ($c->escola_id)<span class="badge badge-suave">Escola</span>@else<span class="badge text-bg-light border">Referência provincial</span>@endif</td>
                        <td><span class="estado text-{{ $c->activo ? 'success' : 'secondary' }}">{{ $c->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="accoes">
                            @if ($controlador->podeEditar($c->escola_id))
                                <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalCurso"
                                        data-accao="{{ route('academico.cursos.update', $c->id) }}"
                                        data-preencher="{{ json_encode($c->only('nivel_ensino_id', 'nome', 'codigo', 'descricao')) }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('academico.cursos.estado', $c->id) }}" class="d-inline">@csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $c->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $c->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                </form>
                            @else
                                <i class="bi bi-lock text-secondary" title="Referência provincial: apenas a administração central pode alterar"></i>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabela-vazia"><i class="bi bi-mortarboard"></i>Sem cursos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($podeCriar)
        <x-modal-form id="modalCurso" titulo-criar="Novo curso" titulo-editar="Editar curso" :accao-criar="route('academico.cursos.store')">
            @include('academico._ambito-registo', ['id' => 'Curso'])
            <div class="mb-3">
                <label class="form-label obrigatorio">Nível de ensino</label>
                <select name="nivel_ensino_id" class="form-select @error('nivel_ensino_id') is-invalid @enderror">
                    @foreach ($niveis->where('exige_curso', true) as $n)<option value="{{ $n->id }}" @selected(old('nivel_ensino_id') == $n->id)>{{ $n->nome }}</option>@endforeach
                </select>
                <x-campo-erro campo="nivel_ensino_id" />
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label obrigatorio">Nome</label>
                    <input name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}">
                    <x-campo-erro campo="nome" />
                </div>
                <div class="col-md-4">
                    <label class="form-label obrigatorio">Código</label>
                    <input name="codigo" class="form-control text-uppercase @error('codigo') is-invalid @enderror" value="{{ old('codigo') }}">
                    <x-campo-erro campo="codigo" />
                </div>
            </div>
            <label class="form-label">Descrição</label>
            <textarea name="descricao" rows="2" class="form-control">{{ old('descricao') }}</textarea>
        </x-modal-form>
    @endif
</x-app-layout>
