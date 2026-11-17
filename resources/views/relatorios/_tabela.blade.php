<table class="{{ $html ? 'table table-sm table-hover mb-0' : 'tabela' }}">
    <thead><tr>@foreach ($colunas as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($linhas as $l)
        <tr>@foreach ($l as $v)<td class="{{ is_numeric($v) ? 'num' : '' }}">{{ $v }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($colunas) }}">Sem dados para os filtros indicados.</td></tr>
    @endforelse
    </tbody>
    @if ($linhas->isNotEmpty() && collect($linhas->first())->skip(1)->contains(fn ($v) => is_int($v)))
        <tfoot><tr>
            <td><strong>Total</strong></td>
            @foreach (collect($linhas->first())->keys()->skip(1) as $k)
                <td class="num"><strong>{{ $linhas->every(fn ($l) => is_int($l[$k] ?? null)) ? number_format($linhas->sum(fn ($l) => $l[$k]), 0, ',', ' ') : '' }}</strong></td>
            @endforeach
        </tr></tfoot>
    @endif
</table>
<style>.num { text-align: right; }</style>
