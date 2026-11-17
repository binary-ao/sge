<x-app-layout titulo="Turnos">
    <x-cabecalho titulo="Configuração Académica" subtitulo="Turnos de funcionamento (referência provincial)"
        :migalhas="['Gestão Académica' => null, 'Configuração Académica' => route('academico.index'), 'Turnos' => null]">
        @can('academico.global')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTurno"><i class="bi bi-plus-lg me-1"></i>Novo turno</button>
        @endcan
    </x-cabecalho>

    @include('academico._nav')
    @php $global = auth()->user()->can('academico.global'); @endphp

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th style="width:70px">Ordem</th><th>Turno</th><th>Horário</th><th>Estado</th>@if ($global)<th></th>@endif</tr></thead>
                <tbody>
                @forelse ($turnos as $t)
                    <tr class="{{ $t->activo ? '' : 'text-secondary' }}">
                        <td>{{ $t->ordem }}</td>
                        <td class="fw-semibold">{{ $t->nome }}</td>
                        <td class="small">{{ $t->horario ?: '—' }}</td>
                        <td><span class="estado text-{{ $t->activo ? 'success' : 'secondary' }}">{{ $t->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        @if ($global)
                            <td class="accoes">
                                <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalTurno"
                                        data-accao="{{ route('academico.turnos.update', $t) }}"
                                        data-preencher="{{ json_encode(['nome' => $t->nome, 'hora_inicio' => substr((string) $t->hora_inicio, 0, 5), 'hora_fim' => substr((string) $t->hora_fim, 0, 5), 'ordem' => $t->ordem]) }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('academico.turnos.estado', $t) }}" class="d-inline"
                                      data-confirmar="{{ $t->activo ? 'Desactivar o turno '.$t->nome.'? As turmas existentes mantêm-se.' : 'Activar o turno '.$t->nome.'?' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-light btn-icon" title="{{ $t->activo ? 'Desactivar' : 'Activar' }}"><i class="bi bi-{{ $t->activo ? 'toggle-on text-success' : 'toggle-off' }}"></i></button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabela-vazia"><i class="bi bi-clock"></i>Sem turnos registados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($global)
        <x-modal-form id="modalTurno" titulo-criar="Novo turno" titulo-editar="Editar turno" :accao-criar="route('academico.turnos.store')">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label obrigatorio">Nome</label>
                    <input name="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}">
                    <x-campo-erro campo="nome" />
                </div>
                <div class="col-4"><label class="form-label">Início</label><input type="time" name="hora_inicio" class="form-control" value="{{ old('hora_inicio') }}"></div>
                <div class="col-4"><label class="form-label">Fim</label><input type="time" name="hora_fim" class="form-control" value="{{ old('hora_fim') }}"></div>
                <div class="col-4"><label class="form-label">Ordem</label><input type="number" name="ordem" class="form-control" value="{{ old('ordem') }}"></div>
            </div>
        </x-modal-form>
    @endif
</x-app-layout>
