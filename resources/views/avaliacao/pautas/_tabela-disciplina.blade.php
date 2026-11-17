{{-- Pauta de uma disciplina. $modo: html | pdf | excel --}}
@php
    $html = $modo === 'html';
    $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $vt = $config?->variavel_trimestral;
    $qualitativa = $config?->eQualitativa();
    $mostrar = fn ($r, $valor) => $qualitativa && $valor !== null ? ($config->escala->intervaloPara((float) $valor)?->sigla ?? $fmt($valor)) : $fmt($valor);
@endphp
<table class="{{ $html ? 'table table-sm table-bordered align-middle mb-0 tabela-pauta' : 'pauta' }}">
    <thead>
        <tr>
            <th rowspan="2">N.º</th>
            <th rowspan="2">Nome do aluno</th>
            @foreach ([1 => 'I Trimestre', 2 => 'II Trimestre', 3 => 'III Trimestre'] as $t => $r)
                <th colspan="{{ $intermedias->count() + 1 }}" class="text-center">{{ $r }}</th>
            @endforeach
            @if ($exames->isNotEmpty() || $anuais->isNotEmpty())
                <th colspan="{{ $exames->count() + $anuais->count() }}" class="text-center">Anual / Exames</th>
            @endif
            <th rowspan="2" class="text-center">Classif. final</th>
            <th rowspan="2" class="text-center">Situação</th>
        </tr>
        <tr>
            @foreach ([1, 2, 3] as $t)
                @foreach ($intermedias as $c)<th class="text-center">{{ $c }}</th>@endforeach
                <th class="text-center">{{ $vt }}</th>
            @endforeach
            @foreach ($exames as $c)<th class="text-center">{{ $c }}</th>@endforeach
            @foreach ($anuais as $c)<th class="text-center">{{ $c }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
    @foreach ($linhas as $l)
        @php $rs = $l['resultados']; $final = $rs[0] ?? null; @endphp
        <tr>
            <td>{{ $l['numero'] }}</td>
            <td class="nome">{{ $l['aluno']->nome }}</td>
            @foreach ([1, 2, 3] as $t)
                @php $rt = $rs[$t] ?? null; @endphp
                @foreach ($intermedias as $c)<td class="text-center small">{{ $mostrar($rt, $rt?->valores[$c] ?? null) }}</td>@endforeach
                <td class="text-center {{ $rt?->situacao === 'negativa' ? 'negativa' : '' }}"><strong>{{ $rt?->classificacao !== null ? ($qualitativa ? $rt->classificacao_qualitativa : $fmt($rt->classificacao)) : '—' }}</strong></td>
            @endforeach
            @php $vars = $final?->valores ?? []; @endphp
            @foreach ($exames as $c)<td class="text-center">{{ is_array($vars[$c] ?? null) ? '—' : $fmt($vars[$c] ?? null) }}</td>@endforeach
            @foreach ($anuais as $c)<td class="text-center small">{{ $mostrar($final, $vars[$c] ?? null) }}</td>@endforeach
            <td class="text-center {{ $final?->situacao === 'negativa' ? 'negativa' : '' }}">
                <strong>{!! $html ? \App\Services\Avaliacao\ConstrutorPautas::exibir($final, $config) : e(\App\Services\Avaliacao\ConstrutorPautas::exibir($final, $config, false)) !!}</strong>
            </td>
            <td class="text-center">{{ ['positiva' => 'Positiva', 'negativa' => 'Negativa', 'incompleta' => 'Incompleta'][$final?->situacao ?? 'incompleta'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
