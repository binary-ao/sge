<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $recibo->numero }}</title>
    <style>
        @page { margin: 26px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1B2533; }
        .topo { border-bottom: 2px solid #0B2A4A; padding-bottom: 8px; margin-bottom: 12px; }
        .topo .escola { font-size: 13px; font-weight: bold; color: #0B2A4A; }
        .numero { font-size: 15px; font-weight: bold; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .itens th, .itens td { border-bottom: 1px solid #C9D2DD; padding: 5px 4px; }
        .itens th { text-align: left; background: #E6EDF5; }
        .num { text-align: right; }
        .total td { font-size: 12px; font-weight: bold; border-top: 2px solid #0B2A4A; }
        .anulado { position: fixed; top: 38%; left: 10%; font-size: 64px; color: rgba(180, 35, 24, .25); transform: rotate(-25deg); font-weight: bold; }
        .demo { font-size: 8px; color: #7A5300; }
        .rodape { margin-top: 26px; font-size: 8px; color: #5B6779; }
        .assinatura { margin-top: 34px; text-align: center; }
        .assinatura span { border-top: 1px solid #1B2533; padding: 2px 40px 0; }
    </style>
</head>
<body>
    @if ($recibo->estado === 'anulado')<div class="anulado">ANULADO</div>@endif
    <div class="topo">
        <table><tr>
            <td>
                <div class="escola">{{ $recibo->escola->nome }}</div>
                <div>Código {{ $recibo->escola->codigo }} · {{ $recibo->escola->municipio->nome }}, Bengo</div>
                <div>{{ $recibo->escola->telefone }}{{ $recibo->escola->email ? ' · '.$recibo->escola->email : '' }}</div>
            </td>
            <td class="numero">RECIBO<br>{{ $recibo->numero }}<br><span style="font-size:10px; font-weight:normal">{{ $recibo->data->format('d/m/Y') }}</span></td>
        </tr></table>
    </div>

    <p>Recebemos de <strong>{{ $recibo->aluno->nome }}</strong> (processo n.º {{ $recibo->aluno->numero_processo }}) a quantia abaixo discriminada:</p>

    <table class="itens">
        <thead><tr><th>Descrição</th><th class="num">Valor</th></tr></thead>
        <tbody>@foreach ($recibo->pagamentos as $p)<tr><td>{{ $p->descricao }}</td><td class="num">{{ \App\Services\Administrativo\ServicoPagamentos::kz($p->valor) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="total"><td>Total</td><td class="num">{{ \App\Services\Administrativo\ServicoPagamentos::kz($recibo->total) }}</td></tr></tfoot>
    </table>

    <p>Forma de pagamento: {{ $recibo->forma_label }}{{ $recibo->referencia ? ' · Referência: '.$recibo->referencia : '' }}</p>
    @if ($recibo->estado === 'anulado')
        <p style="color:#B42318"><strong>Recibo anulado</strong> em {{ $recibo->anulado_em->format('d/m/Y H:i') }} por {{ $recibo->anuladoPor?->nome }}. Motivo: {{ $recibo->motivo_anulacao }}</p>
    @endif

    <div class="assinatura"><span>{{ $recibo->emitidoPor?->nome ?? 'Os Serviços Administrativos' }}</span></div>

    <div class="rodape">
        Emitido por {{ config('sige.nome_completo') }} em {{ now()->format('d/m/Y H:i') }}.
        @if (config('sige.modo_demonstracao'))<br><span class="demo">DOCUMENTO DE DEMONSTRAÇÃO — dados fictícios, sem validade.</span>@endif
    </div>
</body>
</html>
