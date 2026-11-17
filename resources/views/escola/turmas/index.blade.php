<x-app-layout titulo="Turmas">
    <x-cabecalho titulo="Turmas" :subtitulo="$escolaActiva->nome" :migalhas="['Gestão Académica' => null, 'Turmas' => null]">
        @can('turmas.gerir')
            <a href="{{ route('turmas.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova turma</a>
        @endcan
    </x-cabecalho>

    @if ($semTurma > 0 && auth()->user()->can('matriculas.gerir'))
        <div class="alert alert-warning small d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>Existem <strong>{{ $semTurma }}</strong> matrícula(s) activa(s) sem turma. Abra a turma pretendida para distribuir os alunos.</span>
            <a href="{{ route('matriculas.index', ['ano_lectivo_id' => $anoId, 'turma' => 'sem']) }}" class="ms-auto">Ver matrículas</a>
        </div>
    @endif

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Ano lectivo</label>
            <select name="ano_lectivo_id" class="form-select" data-auto-submit>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($anoId == $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($classes as $c)<option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nome_completo }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Turno</label>
            <select name="turno_id" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($turnos as $t)<option value="{{ $t->id }}" @selected(request('turno_id') == $t->id)>{{ $t->nome }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="row g-3">
        @forelse ($turmas as $t)
            @php $ocupacao = $t->capacidade ? round($t->matriculas_activas_count / $t->capacidade * 100) : 0; @endphp
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('turmas.show', $t) }}" class="card h-100 text-decoration-none text-reset">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <div class="fw-semibold text-primary fs-6">{{ $t->classe->nome }} — Turma {{ $t->nome }}</div>
                                <div class="small text-secondary">{{ $t->classe->nivelEnsino->nome }}{{ $t->curso ? ' · '.$t->curso->nome : '' }}</div>
                            </div>
                            @unless ($t->activo)<span class="badge text-bg-secondary">Inactiva</span>@endunless
                        </div>
                        <div class="d-flex flex-wrap gap-3 small text-secondary my-2">
                            <span><i class="bi bi-clock me-1"></i>{{ $t->turno->nome }}</span>
                            <span><i class="bi bi-door-open me-1"></i>{{ $t->sala ?? 'Sem sala' }}</span>
                            <span><i class="bi bi-gender-female me-1"></i>{{ $t->raparigas_count }} raparigas</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $t->matriculas_activas_count }} / {{ $t->capacidade }} alunos</span><span>{{ $ocupacao }}%</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar {{ $ocupacao >= 100 ? 'bg-danger' : ($ocupacao >= 85 ? 'bg-warning' : 'bg-primary') }}" style="width: {{ min(100, $ocupacao) }}%"></div>
                        </div>
                        <div class="small text-secondary mt-2"><i class="bi bi-person-badge me-1"></i>Director de turma: {{ $t->directorTurma?->nome ?? 'por designar' }}</div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12"><div class="card"><div class="card-body tabela-vazia"><i class="bi bi-grid-3x3-gap"></i>Nenhuma turma para os filtros indicados.</div></div></div>
        @endforelse
    </div>
</x-app-layout>
