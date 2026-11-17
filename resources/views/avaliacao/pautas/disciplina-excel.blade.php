<table>
    <tr><td colspan="6"><strong>{{ $titulo }} — {{ $turma->designacao }}</strong></td></tr>
    <tr><td colspan="6">{{ $escola->nome }} · Ano lectivo {{ $turma->anoLectivo->designacao }}</td></tr>
    <tr><td></td></tr>
</table>
@if ($config)
    @include('avaliacao.pautas._tabela-disciplina', ['modo' => 'excel'])
@endif
