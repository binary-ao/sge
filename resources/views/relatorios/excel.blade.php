<table>
    <tr><td colspan="{{ count($colunas) }}"><strong>{{ $titulo }}</strong></td></tr>
    <tr><td colspan="{{ count($colunas) }}">{{ config('sige.entidade') }} · {{ $escola ? $escola->nome : auth()->user()->ambito_descricao }}</td></tr>
    <tr><td></td></tr>
</table>
<table>
    <thead><tr>@foreach ($colunas as $c)<th><strong>{{ $c }}</strong></th>@endforeach</tr></thead>
    <tbody>
    @foreach ($linhas as $l)
        <tr>@foreach ($l as $v)<td>{{ $v }}</td>@endforeach</tr>
    @endforeach
    </tbody>
</table>
<table>
    <tr><td></td></tr>
    @foreach ($notas as $n)<tr><td colspan="{{ count($colunas) }}">{{ $n }}</td></tr>@endforeach
    <tr><td colspan="{{ count($colunas) }}">Gerado em {{ $gerado_em->format('d/m/Y H:i') }} por {{ $utilizador }}</td></tr>
</table>
