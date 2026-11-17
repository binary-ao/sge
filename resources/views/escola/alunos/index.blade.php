<x-app-layout titulo="Alunos">
    <x-cabecalho titulo="Alunos" :subtitulo="$escolaActiva->nome.' · Ano lectivo '.($ano?->designacao ?? '—')"
        :migalhas="['Gestão Académica' => null, 'Alunos' => null]">
        @can('alunos.gerir')
            <a href="{{ route('alunos.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Cadastrar aluno</a>
        @endcan
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><x-cartao-estatistico rotulo="Alunos activos" :valor="$totais['activos']" icone="people" :href="route('alunos.index')" /></div>
        <div class="col-sm-6 col-xl-3"><x-cartao-estatistico rotulo="Activos sem matrícula em {{ $ano?->designacao }}" :valor="$totais['semMatricula']" icone="exclamation-circle" tom="alerta" :href="route('alunos.index', ['matricula' => 'sem'])" /></div>
    </div>

    <form method="GET" class="barra-filtros">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Pesquisar</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nome, n.º de processo ou BI">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Classe ({{ $ano?->designacao }})</label>
                <select name="classe_id" class="form-select" data-auto-submit>
                    <option value="">Todas</option>
                    @foreach ($classes as $c)<option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nome_completo }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Turma</label>
                <select name="turma_id" class="form-select" data-auto-submit>
                    <option value="">Todas</option>
                    @foreach ($turmas as $t)<option value="{{ $t->id }}" @selected(request('turma_id') == $t->id)>{{ $t->designacao }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-4">
                <label class="form-label">Género</label>
                <select name="genero" class="form-select" data-auto-submit>
                    <option value="">Todos</option><option value="M" @selected(request('genero') === 'M')>M</option><option value="F" @selected(request('genero') === 'F')>F</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Situação</label>
                <select name="estado" class="form-select" data-auto-submit>
                    @foreach (\App\Models\Aluno::ESTADOS as $v => $r)<option value="{{ $v }}" @selected(request('estado', 'activo') === $v)>{{ $r }}</option>@endforeach
                    <option value="todos" @selected(request('estado') === 'todos')>Todas</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Matrícula {{ $ano?->designacao }}</label>
                <select name="matricula" class="form-select" data-auto-submit>
                    <option value="">Indiferente</option>
                    <option value="sem" @selected(request('matricula') === 'sem')>Sem matrícula</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Aplicar</button>
            @if (request()->hasAny(['q', 'classe_id', 'turma_id', 'genero', 'estado', 'matricula']))<a href="{{ route('alunos.index') }}" class="btn btn-light btn-sm">Limpar</a>@endif
            <span class="ms-auto small text-secondary align-self-center">{{ $alunos->total() }} aluno(s)</span>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Processo</th><th>Aluno</th><th>Idade</th><th>Classe / Turma ({{ $ano?->designacao }})</th><th>Encarregado</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                @forelse ($alunos as $a)
                    @php $m = $a->matriculas->first(); @endphp
                    <tr>
                        <td><code>{{ $a->numero_processo }}</code></td>
                        <td><a href="{{ route('alunos.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->nome }}</a>
                            <div class="small text-secondary">{{ $a->genero === 'F' ? 'Feminino' : 'Masculino' }}</div></td>
                        <td>{{ $a->idade }}</td>
                        <td class="small">
                            @if ($m)
                                {{ $m->classe->nome }}{{ $m->curso ? ' · '.$m->curso->codigo : '' }}
                                <br>@if ($m->turma)<span class="text-secondary">Turma {{ $m->turma->nome }}</span>@else<span class="text-warning">Sem turma</span>@endif
                            @else
                                <span class="text-secondary">Sem matrícula</span>
                            @endif
                        </td>
                        <td class="small">{{ $a->encarregado_id ? '✓' : '—' }}</td>
                        <td><span class="estado text-{{ $a->estado_cor }}">{{ $a->estado_label }}</span></td>
                        <td class="accoes">
                            <a href="{{ route('alunos.show', $a) }}" class="btn btn-light btn-icon" title="Ver ficha"><i class="bi bi-eye"></i></a>
                            @can('matriculas.gerir')
                                @if (! $m && $a->estado === 'activo')
                                    <a href="{{ route('matriculas.create', ['aluno' => $a->id]) }}" class="btn btn-light btn-icon" title="Matricular"><i class="bi bi-journal-plus"></i></a>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tabela-vazia"><i class="bi bi-people"></i>Nenhum aluno encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $alunos->links() }}</div>
</x-app-layout>
