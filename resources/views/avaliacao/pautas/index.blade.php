<x-app-layout titulo="Pautas">
    <x-cabecalho titulo="Pautas" subtitulo="Pautas trimestrais, finais, por disciplina e por classe" :migalhas="['Avaliação' => null, 'Pautas' => null]" />

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Ano lectivo</label>
            <select name="ano_lectivo_id" class="form-select" data-auto-submit>
                @foreach ($anos as $a)<option value="{{ $a->id }}" @selected($anoId == $a->id)>{{ $a->designacao }}</option>@endforeach
            </select>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h2 class="titulo">Pautas por turma</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Turma</th><th class="text-end">Alunos</th><th>Trimestrais</th><th>Final</th></tr></thead>
                        <tbody>
                        @forelse ($turmas as $t)
                            <tr>
                                <td class="fw-semibold">{{ $t->designacao }}<div class="small text-secondary fw-normal">{{ $t->classe->nivelEnsino->nome }} · {{ $t->turno->nome }}</div></td>
                                <td class="text-end">{{ $t->matriculas_activas_count }}</td>
                                <td>
                                    @foreach ([1 => 'I', 2 => 'II', 3 => 'III'] as $p => $r)
                                        <a href="{{ route('avaliacao.pautas.turma', [$t, 'periodo' => $p]) }}" class="btn btn-sm btn-light">{{ $r }} Trim.</a>
                                    @endforeach
                                </td>
                                <td><a href="{{ route('avaliacao.pautas.turma', [$t, 'periodo' => 0]) }}" class="btn btn-sm btn-outline-primary">Pauta final</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabela-vazia">Sem turmas disponíveis.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if ($classes->isNotEmpty())
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h2 class="titulo">Pauta por classe</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($classes as $c)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="small">{{ $c->nome_completo }}</span>
                                <a href="{{ route('avaliacao.pautas.classe', [$c, 'ano_lectivo_id' => $anoId]) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-right"></i></a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
