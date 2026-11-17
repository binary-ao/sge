<x-app-layout titulo="Lançamento de notas">
    <x-cabecalho titulo="Lançamento de notas" :subtitulo="'Ano lectivo '.($ano?->designacao ?? '—')"
        :migalhas="['Avaliação' => null, 'Lançamento de notas' => null]">
        @can('pautas.ver')<a href="{{ route('avaliacao.pautas.index') }}" class="btn btn-light"><i class="bi bi-table me-1"></i>Pautas</a>@endcan
    </x-cabecalho>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($periodos as $p)
            <a href="{{ route('avaliacao.lancamento.index', ['periodo' => $p->periodo]) }}"
               class="btn btn-sm {{ $periodo === $p->periodo ? 'btn-primary' : 'btn-light' }}">
                {{ $p->rotulo }}
                @if ($p->estado === 'fechado')<i class="bi bi-lock-fill ms-1" title="Período fechado"></i>@endif
            </a>
        @endforeach
    </div>

    @if (! $linhas)
        <div class="card"><div class="card-body tabela-vazia">
            <i class="bi bi-pencil-square"></i>
            @if (auth()->user()->perfil->codigo === 'professor')
                Não tem disciplinas atribuídas nesta escola em {{ $ano?->designacao }}. As atribuições são feitas pela direcção, na página de cada turma.
            @else
                Sem turmas com plano curricular neste ano lectivo.
            @endif
        </div></div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Turma</th><th>Disciplina</th><th>Turno</th><th class="text-end">Notas lançadas ({{ \App\Models\PeriodoLancamento::PERIODOS[$periodo] }})</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($linhas as $l)
                        @php $n = $contagens[$l['turma']->id.'-'.$l['disciplina']->id] ?? 0; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $l['turma']->designacao }}<div class="small text-secondary fw-normal">{{ $l['turma']->classe->nivelEnsino->nome }}</div></td>
                            <td>{{ $l['disciplina']->nome }}</td>
                            <td class="small">{{ $l['turma']->turno->nome }}</td>
                            <td class="text-end">{{ $n ?: '—' }}</td>
                            <td class="accoes">
                                @if ($l['configurada'])
                                    <a href="{{ route('avaliacao.lancamento.grelha', [$l['turma'], $l['disciplina']->id, 'periodo' => $periodo]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square me-1"></i>Abrir grelha
                                    </a>
                                @else
                                    <span class="badge text-bg-warning" title="Não existe configuração de avaliação activa para esta classe">Sem configuração</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
