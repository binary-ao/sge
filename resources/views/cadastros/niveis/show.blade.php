<x-app-layout :titulo="$nivel->nome">
    @php $gerir = auth()->user()->can('rede.catalogos'); @endphp
    <x-cabecalho :titulo="$nivel->nome" :subtitulo="$nivel->descricao"
        :migalhas="['Cadastros e Configurações' => null, 'Níveis de Ensino' => route('cadastros.niveis.index'), $nivel->nome => null]">
        @if ($gerir)
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalNivel"
                    data-accao="{{ route('cadastros.niveis.update', $nivel) }}"
                    data-preencher="{{ json_encode($nivel->only('nome', 'codigo', 'descricao', 'ordem', 'exige_curso')) }}"><i class="bi bi-pencil me-1"></i>Editar</button>
            <form method="POST" action="{{ route('cadastros.niveis.estado', $nivel) }}"
                  data-confirmar="{{ $nivel->activo ? 'Desactivar este nível de ensino?' : 'Activar este nível de ensino?' }}"
                  data-confirmar-classe="{{ $nivel->activo ? 'btn-danger' : 'btn-success' }}" data-confirmar-texto="{{ $nivel->activo ? 'Desactivar' : 'Activar' }}">
                @csrf @method('PATCH')
                <button class="btn {{ $nivel->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $nivel->activo ? 'Desactivar' : 'Activar' }}</button>
            </form>
        @endif
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Escolas activas" :valor="$escolas->total()" icone="building" /></div>
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Ciclos" :valor="$nivel->ciclos->count()" icone="diagram-2" /></div>
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Classes" :valor="$nivel->classes->count()" icone="list-ol" /></div>
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Disciplinas" :valor="$disciplinas->count()" icone="book" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Identificação</h2>
                    <span class="estado text-{{ $nivel->activo ? 'success' : 'secondary' }}">{{ $nivel->activo ? 'Activo' : 'Inactivo' }}</span></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Código</dt><dd class="col-7"><code>{{ $nivel->codigo }}</code></dd>
                        <dt class="col-5 text-secondary fw-normal">Ordem</dt><dd class="col-7">{{ $nivel->ordem }}</dd>
                        <dt class="col-5 text-secondary fw-normal">Exige curso</dt><dd class="col-7">{{ $nivel->exige_curso ? 'Sim' : 'Não' }}</dd>
                    </dl>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Ciclos e classes</h2>
                    <a href="{{ route('cadastros.classes.index', ['nivel_id' => $nivel->id]) }}" class="small">Gerir</a></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($nivel->classes as $c)
                        <li class="list-group-item d-flex justify-content-between {{ $c->activo ? '' : 'text-secondary' }}">
                            <a href="{{ route('cadastros.classes.show', $c) }}" class="text-decoration-none">{{ $c->nome }}</a>
                            <span class="text-secondary">{{ $c->ciclo?->nome ?? '—' }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">Sem classes registadas.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="titulo">Disciplinas</h2>
                    <a href="{{ route('cadastros.disciplinas.index', ['nivel_id' => $nivel->id]) }}" class="small">Ver todas</a></div>
                <div class="card-body">
                    @forelse ($disciplinas as $d)
                        <a href="{{ route('cadastros.disciplinas.show', $d->id) }}" class="badge text-bg-light border text-decoration-none fw-normal me-1 mb-1 {{ $d->activo ? '' : 'opacity-50' }}">{{ $d->nome }}</a>
                    @empty
                        <span class="small text-secondary">Sem disciplinas associadas.</span>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><h2 class="titulo">Escolas que ministram este nível</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Escola</th><th>Município</th><th>Tipologia</th></tr></thead>
                        <tbody>
                        @forelse ($escolas as $e)
                            <tr>
                                <td>@can('escolas.ver')<a href="{{ route('escolas.show', $e) }}" class="text-decoration-none">{{ $e->nome }}</a>@else{{ $e->nome }}@endcan</td>
                                <td class="small">{{ $e->municipio->nome }}</td>
                                <td class="small">{{ $e->tipologia->nome }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tabela-vazia"><i class="bi bi-building"></i>Nenhuma escola no seu âmbito ministra este nível.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $escolas->links() }}</div>
        </div>
    </div>

    @if ($gerir)
        @include('cadastros.niveis._modal')
    @endif
</x-app-layout>
