{{-- Tabela da pauta da turma. $modo: html | pdf | excel --}}
@php
    use App\Services\Avaliacao\ConstrutorPautas;
    $html = $modo === 'html';
    $final = $tipo === 'final';
@endphp
<table class="{{ $html ? 'table table-sm table-bordered align-middle mb-0 tabela-pauta' : 'pauta' }}">
    <thead>
        <tr>
            <th>N.º</th>
            <th>Nome do aluno</th>
            <th>Proc.</th>
            <th>G</th>
            @foreach ($disciplinas as $d)
                <th class="text-center" title="{{ $d->nome }}">{{ $d->sigla }}</th>
            @endforeach
            @if ($final)
                <th>Média</th>
                <th>Resultado</th>
            @endif
        </tr>
    </thead>
    <tbody>
    @foreach ($linhas as $l)
        <tr>
            <td>{{ $l['numero'] }}</td>
            <td class="nome">{{ $l['aluno']->nome }}</td>
            <td>{{ $l['aluno']->numero_processo }}</td>
            <td>{{ $l['aluno']->genero }}</td>
            @foreach ($disciplinas as $d)
                @php $r = $l['notas'][$d->id]; @endphp
                <td class="text-center {{ $r?->situacao === 'negativa' ? 'negativa' : '' }}">
                    {!! $html ? \App\Services\Avaliacao\ConstrutorPautas::exibir($r, $config) : e(\App\Services\Avaliacao\ConstrutorPautas::exibir($r, $config, false)) !!}
                </td>
            @endforeach
            @if ($final)
                <td class="text-center">{{ $l['final']?->media !== null ? number_format($l['final']->media, 1, ',', '') : '—' }}</td>
                <td class="resultado">
                    @if ($l['final'])
                        @if ($html)
                            <span class="badge text-bg-{{ $l['final']->cor }}" data-bs-toggle="tooltip" data-bs-title="{{ implode(' ', $l['final']->justificacao) }}">{{ $l['final']->rotulo }}</span>
                        @else
                            {{ $l['final']->rotulo }}
                        @endif
                    @else — @endif
                </td>
            @endif
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-end"><strong>Positivas / Negativas</strong></td>
            @foreach ($disciplinas as $d)
                <td class="text-center small">{{ $estatisticas[$d->id]['positivas'] }} / {{ $estatisticas[$d->id]['negativas'] }}</td>
            @endforeach
            @if ($final)<td colspan="2"></td>@endif
        </tr>
        <tr>
            <td colspan="4" class="text-end"><strong>Média da turma</strong></td>
            @foreach ($disciplinas as $d)
                <td class="text-center small">{{ $estatisticas[$d->id]['media'] !== null ? number_format($estatisticas[$d->id]['media'], 1, ',', '') : '—' }}</td>
            @endforeach
            @if ($final)<td colspan="2"></td>@endif
        </tr>
    </tfoot>
</table>
