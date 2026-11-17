<x-app-layout titulo="Matrículas">
    <x-cabecalho titulo="Matrículas" :subtitulo="$escolaActiva->nome" :migalhas="['Gestão Académica' => null, 'Matrículas' => null]">
        @can('matriculas.gerir')
            <a href="{{ route('matriculas.create') }}" class="btn btn-primary"><i class="bi bi-journal-plus me-1"></i>Nova matrícula</a>
        @endcan
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Matrículas no ano" :valor="$resumo['total']" icone="journal-check" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Activas" :valor="$resumo['activas']" icone="check2-circle" tom="sucesso" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Novas matrículas" :valor="$resumo['novas']" icone="person-plus" tom="info" /></div>
        <div class="col-6 col-xl-3"><x-cartao-estatistico rotulo="Activas sem turma" :valor="$resumo['semTurma']" icone="exclamation-triangle" tom="alerta" :href="route('matriculas.index', ['ano_lectivo_id' => $anoId, 'turma' => 'sem'])" /></div>
    </div>

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Ano lectivo</label>
            <select name="ano_lectivo_id" class="form-select" data-auto-submit>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($anoId == $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-8">
            <label class="form-label">Pesquisar</label>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="N.º de matrícula, aluno ou processo">
        </div>
        <div class="col-lg-3 col-md-4">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($classes as $c)<option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nome_completo }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select" data-auto-submit>
                <option value="">Todos</option>
                @foreach (\App\Models\Matricula::ESTADOS as $v => $r)<option value="{{ $v }}" @selected(request('estado') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <label class="form-label">Turma</label>
            <select name="turma" class="form-select" data-auto-submit>
                <option value="">Indiferente</option>
                <option value="sem" @selected(request('turma') === 'sem')>Sem turma</option>
            </select>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>N.º</th><th>Aluno</th><th>Classe / Curso</th><th>Turma</th><th>Turno</th><th>Tipo</th><th>Data</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($matriculas as $m)
                    <tr>
                        <td><code>{{ $m->numero }}</code></td>
                        <td><a href="{{ route('alunos.show', $m->aluno_id) }}" class="fw-semibold text-decoration-none">{{ $m->aluno->nome }}</a>
                            <div class="small text-secondary">Processo {{ $m->aluno->numero_processo }}</div></td>
                        <td class="small">{{ $m->classe->nome }}{{ $m->curso ? ' · '.$m->curso->codigo : '' }}</td>
                        <td>@if ($m->turma){{ $m->turma->nome }}@elseif ($m->estado === 'activa')<span class="badge text-bg-warning">Por atribuir</span>@else — @endif</td>
                        <td class="small">{{ $m->turno->nome }}</td>
                        <td class="small">{{ $m->tipo_label }}</td>
                        <td class="small">{{ $m->data_matricula->format('d/m/Y') }}</td>
                        <td><span class="estado text-{{ $m->estado_cor }}">{{ $m->estado_label }}</span></td>
                        <td class="accoes">
                            @can('matriculas.gerir')
                                @if ($m->estado === 'activa')<a href="{{ route('matriculas.edit', $m) }}" class="btn btn-light btn-icon" title="Editar"><i class="bi bi-pencil"></i></a>@endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="tabela-vazia"><i class="bi bi-journal"></i>Sem matrículas para os filtros indicados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $matriculas->links() }}</div>
</x-app-layout>
