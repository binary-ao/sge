<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 22px 26px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1B2533; }
        .cabecalho { border-bottom: 2px solid #0B2A4A; padding-bottom: 6px; margin-bottom: 8px; }
        .cabecalho .rep { font-size: 8px; color: #5B6779; text-transform: uppercase; letter-spacing: .5px; }
        .cabecalho h1 { font-size: 13px; margin: 2px 0; color: #0B2A4A; }
        .meta td { padding: 1px 10px 1px 0; font-size: 8.5px; }
        table.pauta { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.pauta th, table.pauta td { border: 1px solid #9AA6B5; padding: 2px 3px; }
        table.pauta th { background: #E6EDF5; color: #0B2A4A; font-size: 8px; }
        table.pauta td.nome { white-space: nowrap; }
        table.pauta td.negativa { color: #B42318; font-weight: bold; }
        table.pauta tfoot td { background: #F3F5F8; }
        .text-center { text-align: center; } .text-end { text-align: right; } .small { font-size: 7.5px; }
        .assinaturas { width: 100%; margin-top: 26px; }
        .assinaturas td { width: 33%; text-align: center; padding-top: 22px; font-size: 8px; }
        .assinaturas span { border-top: 1px solid #1B2533; padding: 2px 20px 0; }
        .rodape { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7px; color: #5B6779; border-top: 1px solid #E1E6ED; padding-top: 3px; }
        .demo { color: #7A5300; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <div class="rep">República de Angola · {{ config('sige.entidade') }}</div>
        <h1>{{ $titulo }}</h1>
        <table class="meta">
            <tr>
                <td><strong>Escola:</strong> {{ $escola->nome }} ({{ $escola->codigo }})</td>
                <td><strong>Município:</strong> {{ $escola->municipio->nome }}</td>
                <td><strong>Ano lectivo:</strong> {{ $turma->anoLectivo->designacao }}</td>
            </tr>
            <tr>
                <td><strong>Classe:</strong> {{ $turma->classe->nome }} — {{ $turma->classe->nivelEnsino->nome }}{{ $turma->curso ? ' · '.$turma->curso->nome : '' }}</td>
                <td><strong>Turma:</strong> {{ $turma->nome }} · {{ $turma->turno->nome }}</td>
                <td>@isset($disciplina)<strong>Disciplina:</strong> {{ $disciplina->nome }}@endisset</td>
            </tr>
        </table>
    </div>

    @yield('conteudo')

    <table class="assinaturas">
        <tr>@foreach (config('avaliacao.pautas.assinaturas') as $a)<td><span>{{ $a }}</span></td>@endforeach</tr>
    </table>

    <div class="rodape">
        {{ config('sige.nome_completo') }} · Gerado em {{ $gerado_em->format('d/m/Y H:i') }}
        @if ($config) · Configuração: {{ $config->nome }} (v{{ $config->versao }})@endif
        @if (config('sige.modo_demonstracao')) · <span class="demo">DOCUMENTO DE DEMONSTRAÇÃO — dados fictícios</span>@endif
    </div>
</body>
</html>
