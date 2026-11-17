<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 26px 30px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1B2533; }
        .cabecalho { border-bottom: 2px solid #0B2A4A; padding-bottom: 6px; margin-bottom: 10px; }
        .cabecalho .rep { font-size: 8px; color: #5B6779; text-transform: uppercase; letter-spacing: .5px; }
        .cabecalho h1 { font-size: 14px; margin: 3px 0; color: #0B2A4A; }
        table.tabela { width: 100%; border-collapse: collapse; }
        table.tabela th, table.tabela td { border: 1px solid #A9B4C2; padding: 3px 5px; }
        table.tabela th { background: #E6EDF5; color: #0B2A4A; text-align: left; }
        table.tabela tfoot td { background: #F3F5F8; }
        .num { text-align: right; }
        .notas { margin-top: 8px; font-size: 8px; color: #5B6779; }
        .rodape { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7px; color: #5B6779; border-top: 1px solid #E1E6ED; padding-top: 3px; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <div class="rep">República de Angola · {{ config('sige.entidade') }}</div>
        <h1>{{ $titulo }}</h1>
        <div>{{ $escola ? $escola->nome.' ('.$escola->codigo.')' : 'Âmbito: '.auth()->user()->ambito_descricao }}</div>
    </div>
    @include('relatorios._tabela', ['html' => false])
    <div class="notas">@foreach ($notas as $n)<div>{{ $n }}</div>@endforeach</div>
    <div class="rodape">{{ config('sige.nome_completo') }} · Gerado em {{ $gerado_em->format('d/m/Y H:i') }} por {{ $utilizador }}</div>
</body>
</html>
