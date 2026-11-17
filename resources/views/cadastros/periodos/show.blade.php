<x-app-layout :titulo="$trimestre->designacao.' '.$trimestre->anoLectivo->designacao">
    @php
        $global = auth()->user()->can('academico.global');
        [$rotulo, $cor] = \App\Models\Trimestre::SITUACOES[$trimestre->situacao];
    @endphp
    <x-cabecalho :titulo="$trimestre->designacao.' — '.$trimestre->anoLectivo->designacao"
        :subtitulo="($trimestre->data_inicio ? $trimestre->data_inicio->format('d/m/Y').' a '.$trimestre->data_fim?->format('d/m/Y') : 'Datas por definir').' · '.$rotulo"
        :migalhas="['Cadastros e Configurações' => null, 'Períodos de Avaliação' => route('cadastros.periodos.index', ['ano_lectivo_id' => $trimestre->ano_lectivo_id]), $trimestre->designacao => null]">
        @if ($global)
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalPeriodo"
                    data-accao="{{ route('cadastros.periodos.update', $trimestre) }}"
                    data-preencher="{{ json_encode(['designacao' => $trimestre->designacao, 'data_inicio' => $trimestre->data_inicio?->toDateString(), 'data_fim' => $trimestre->data_fim?->toDateString()]) }}"><i class="bi bi-pencil me-1"></i>Editar datas</button>
        @endif
        @can('pautas.fechar')
            <a href="{{ route('avaliacao.periodos.index', ['ano_lectivo_id' => $trimestre->ano_lectivo_id]) }}" class="btn btn-light"><i class="bi bi-lock me-1"></i>Fecho de períodos da escola</a>
        @endcan
    </x-cabecalho>

    <div class="row g-3 mb-3">
        @foreach ($trimestre->anoLectivo->trimestres as $t)
            @php [$r, $c] = \App\Models\Trimestre::SITUACOES[$t->situacao]; @endphp
            <div class="col-md-4">
                <a href="{{ route('cadastros.periodos.show', $t) }}" class="card text-decoration-none h-100 {{ $t->id === $trimestre->id ? 'border-primary' : '' }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between"><strong>{{ $t->designacao }}</strong><span class="badge text-bg-{{ $c }}">{{ $r }}</span></div>
                        <div class="small text-secondary">{{ $t->data_inicio?->format('d/m/Y') ?? '—' }} a {{ $t->data_fim?->format('d/m/Y') ?? '—' }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="q">Pesquisar escola</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome ou código">
        </div>
        @if ($municipios->count() > 1)
            <div class="col-md-3">
                <label class="form-label">Município</label>
                <select name="municipio_id" class="form-select" data-auto-submit>
                    <option value="">Todos</option>
                    @foreach ($municipios as $m)<option value="{{ $m->id }}" @selected(request('municipio_id') == $m->id)>{{ $m->nome }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="col-md-2">
            <label class="form-label">Lançamento</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                <option value="aberto" @selected(request('estado') === 'aberto')>Aberto</option>
                <option value="fechado" @selected(request('estado') === 'fechado')>Fechado</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Pesquisar</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="titulo">Lançamento de notas por escola</h2></div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Escola</th><th>Município</th><th>Lançamento</th><th>Última alteração</th></tr></thead>
                <tbody>
                @forelse ($escolas as $e)
                    @php $reg = $estados[$e->id] ?? null; $fechado = $reg?->estado === 'fechado'; @endphp
                    <tr>
                        <td>@can('escolas.ver')<a href="{{ route('escolas.show', $e) }}" class="text-decoration-none">{{ $e->nome }}</a>@else{{ $e->nome }}@endcan</td>
                        <td class="small">{{ $e->municipio->nome }}</td>
                        <td><span class="estado text-{{ $fechado ? 'secondary' : 'success' }}">{{ $fechado ? 'Fechado' : 'Aberto' }}</span></td>
                        <td class="small text-secondary">
                            @if ($reg?->alterado_em){{ $reg->alterado_em->format('d/m/Y H:i') }} · {{ $reg->alteradoPor?->nome ?? '—' }}@else—@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="tabela-vazia"><i class="bi bi-building"></i>Nenhuma escola encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $escolas->links() }}</div>

    @if ($global)
        <x-modal-form id="modalPeriodo" titulo-criar="Editar período" titulo-editar="Editar período" :accao-criar="route('cadastros.periodos.update', $trimestre)">
            <div class="mb-3">
                <label class="form-label" for="perDesignacao">Designação</label>
                <input id="perDesignacao" name="designacao" maxlength="30" class="form-control" value="{{ old('designacao') }}">
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label obrigatorio" for="perInicio">Início</label>
                    <input type="date" id="perInicio" name="data_inicio" class="form-control @error('data_inicio') is-invalid @enderror" value="{{ old('data_inicio') }}">
                    <x-campo-erro campo="data_inicio" />
                </div>
                <div class="col-6">
                    <label class="form-label obrigatorio" for="perFim">Fim</label>
                    <input type="date" id="perFim" name="data_fim" class="form-control @error('data_fim') is-invalid @enderror" value="{{ old('data_fim') }}">
                    <x-campo-erro campo="data_fim" />
                </div>
            </div>
        </x-modal-form>
    @endif
</x-app-layout>
