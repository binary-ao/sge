<x-app-layout titulo="Anos Lectivos">
    @php $global = auth()->user()->can('academico.global'); @endphp
    <x-cabecalho titulo="Anos Lectivos" subtitulo="Referência provincial comum a todas as escolas. Existe um único ano lectivo activo."
        :migalhas="['Cadastros e Configurações' => null, 'Anos Lectivos' => null]">
        @if ($global)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAno"><i class="bi bi-plus-lg me-1"></i>Novo ano lectivo</button>
        @endif
    </x-cabecalho>

    <div class="alert alert-light border small py-2 d-flex gap-2 align-items-start">
        <i class="bi bi-calendar-check text-primary mt-1"></i>
        <div>
            Ano lectivo activo: <strong>{{ $actual?->designacao ?? 'nenhum definido' }}</strong>.
            É o ano por omissão das matrículas, turmas, avaliações e indicadores. Só pode haver um ano activo e um ano em curso de cada vez;
            os anos lectivos não se eliminam, para preservar o histórico.
        </div>
    </div>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label" for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Designação (ex.: 2026)">
        </div>
        <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach (\App\Models\AnoLectivo::ESTADOS as $v => $r)<option value="{{ $v }}" @selected(request('estado') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Pesquisar</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Ano lectivo</th><th>Início</th><th>Fim</th><th>Períodos definidos</th><th class="text-end">Turmas</th><th class="text-end">Matrículas</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($anos as $ano)
                    @php $definidos = $ano->trimestres->filter(fn ($t) => $t->data_inicio && $t->data_fim)->count(); @endphp
                    <tr>
                        <td>
                            <a href="{{ route('cadastros.anos.show', $ano) }}" class="fw-semibold text-decoration-none">{{ $ano->designacao }}</a>
                            @if ($ano->actual)<span class="badge text-bg-primary ms-1">Activo</span>@endif
                        </td>
                        <td>{{ $ano->data_inicio->format('d/m/Y') }}</td>
                        <td>{{ $ano->data_fim->format('d/m/Y') }}</td>
                        <td>
                            <span class="{{ $definidos < 3 ? 'text-warning' : '' }}">{{ $definidos }} de {{ $ano->trimestres->count() }}</span>
                            @if ($definidos < 3)<i class="bi bi-exclamation-triangle text-warning" title="Há trimestres sem datas"></i>@endif
                        </td>
                        <td class="text-end">{{ $ano->turmas_count }}</td>
                        <td class="text-end">{{ $ano->matriculas_count }}</td>
                        <td><span class="badge {{ ['planeado' => 'text-bg-light border', 'em_curso' => 'text-bg-success', 'encerrado' => 'text-bg-secondary'][$ano->estado] }}">{{ $ano->estado_label }}</span></td>
                        <td class="accoes">
                            <a href="{{ route('cadastros.anos.show', $ano) }}" class="btn btn-light btn-icon" title="Ver"><i class="bi bi-eye"></i></a>
                            @if ($global)
                                <button class="btn btn-light btn-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalAno"
                                        data-accao="{{ route('cadastros.anos.update', $ano) }}"
                                        data-preencher="{{ json_encode(['designacao' => $ano->designacao, 'estado' => $ano->estado, 'data_inicio' => $ano->data_inicio->toDateString(), 'data_fim' => $ano->data_fim->toDateString()]) }}"><i class="bi bi-pencil"></i></button>
                                @unless ($ano->actual)
                                    <form method="POST" action="{{ route('cadastros.anos.actual', $ano) }}" class="d-inline"
                                          data-confirmar="Activar {{ $ano->designacao }}? Passa a ser o ano lectivo activo (por omissão nas turmas, matrículas e indicadores) e o actual deixa de o ser."
                                          data-confirmar-texto="Activar">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-light btn-icon" title="Definir como ano activo"><i class="bi bi-toggle-off"></i></button>
                                    </form>
                                @else
                                    <span class="btn btn-icon" title="Ano lectivo activo"><i class="bi bi-toggle-on text-success"></i></span>
                                @endunless
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tabela-vazia"><i class="bi bi-calendar3"></i>Nenhum ano lectivo encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $anos->links() }}</div>

    @if ($global)
        @include('cadastros.anos._modal')
    @endif
</x-app-layout>
