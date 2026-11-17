<x-app-layout :titulo="'Ano lectivo '.$ano->designacao">
    @php $global = auth()->user()->can('academico.global'); @endphp
    <x-cabecalho :titulo="'Ano lectivo '.$ano->designacao" :subtitulo="$ano->data_inicio->format('d/m/Y').' a '.$ano->data_fim->format('d/m/Y').' · '.$ano->estado_label"
        :migalhas="['Cadastros e Configurações' => null, 'Anos Lectivos' => route('cadastros.anos.index'), $ano->designacao => null]">
        @if ($global)
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalAno"
                    data-accao="{{ route('cadastros.anos.update', $ano) }}"
                    data-preencher="{{ json_encode(['designacao' => $ano->designacao, 'estado' => $ano->estado, 'data_inicio' => $ano->data_inicio->toDateString(), 'data_fim' => $ano->data_fim->toDateString()]) }}"><i class="bi bi-pencil me-1"></i>Editar</button>
            @unless ($ano->actual)
                <form method="POST" action="{{ route('cadastros.anos.actual', $ano) }}"
                      data-confirmar="Activar {{ $ano->designacao }}? Passa a ser o ano lectivo activo e o actual deixa de o ser." data-confirmar-texto="Activar">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-primary"><i class="bi bi-toggle-on me-1"></i>Definir como activo</button>
                </form>
            @endunless
        @endif
    </x-cabecalho>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Situação" :valor="$ano->actual ? 'Activo' : $ano->estado_label" icone="calendar-check" /></div>
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Turmas" :valor="$ano->turmas_count" icone="grid-3x3-gap" /></div>
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Matrículas" :valor="$ano->matriculas_count" icone="journal-check" /></div>
        <div class="col-md-3 col-6"><x-cartao-estatistico rotulo="Escolas activas (âmbito)" :valor="$totalEscolas" icone="building" /></div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="titulo">Períodos de avaliação</h2>
            <a href="{{ route('cadastros.periodos.index', ['ano_lectivo_id' => $ano->id]) }}" class="small">Gerir períodos</a></div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Período</th><th>Início</th><th>Fim</th><th>Situação</th><th class="text-end" title="Escolas com o lançamento de notas fechado">Lançamento fechado</th><th></th></tr></thead>
                <tbody>
                @foreach ($ano->trimestres as $t)
                    @php [$rotulo, $cor] = \App\Models\Trimestre::SITUACOES[$t->situacao]; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $t->designacao }}</td>
                        <td>{{ $t->data_inicio?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $t->data_fim?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $cor }}">{{ $rotulo }}</span></td>
                        <td class="text-end">{{ $fechados[$t->numero] ?? 0 }} / {{ $totalEscolas }}</td>
                        <td class="accoes"><a href="{{ route('cadastros.periodos.show', $t) }}" class="btn btn-light btn-icon" title="Ver situação por escola"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @endforeach
                <tr class="text-secondary">
                    <td>Exames</td><td colspan="3" class="small">Época de exames (sem datas próprias; fecho por escola em Avaliação › Fecho de Períodos)</td>
                    <td class="text-end">{{ $fechados[4] ?? 0 }} / {{ $totalEscolas }}</td><td></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($global)
        @include('cadastros.anos._modal')
    @endif
</x-app-layout>
