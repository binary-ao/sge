<table>
    <tr><td colspan="6"><strong>{{ $titulo }} — {{ $turma->designacao }}</strong></td></tr>
    <tr><td colspan="6">{{ $escola->nome }} ({{ $escola->codigo }}) · {{ $escola->municipio->nome }} · Ano lectivo {{ $turma->anoLectivo->designacao }}</td></tr>
    <tr><td colspan="6">{{ $turma->classe->nivelEnsino->nome }}{{ $turma->curso ? ' · '.$turma->curso->nome : '' }} · Turno: {{ $turma->turno->nome }}</td></tr>
    <tr><td></td></tr>
</table>
@include('avaliacao.pautas._tabela-turma', ['modo' => 'excel'])
<table>
    <tr><td></td></tr>
    @if ($config)<tr><td colspan="6">Configuração: {{ $config->nome }} (versão {{ $config->versao }}) · Gerado em {{ $gerado_em->format('d/m/Y H:i') }}</td></tr>@endif
    @if (config('sige.modo_demonstracao'))<tr><td colspan="6">Documento de demonstração — dados fictícios.</td></tr>@endif
</table>
