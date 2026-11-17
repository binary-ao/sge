<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $d->numero }}</title>
    <style>
        @page { margin: 50px 60px 60px; }
        body { font-family: DejaVu Serif, serif; font-size: 11.5px; color: #1B2533; line-height: 1.6; }
        .topo { text-align: center; border-bottom: 2px solid #0B2A4A; padding-bottom: 10px; margin-bottom: 24px; }
        .topo .rep { font-size: 10px; letter-spacing: 1px; text-transform: uppercase; }
        .topo .escola { font-size: 14px; font-weight: bold; color: #0B2A4A; margin-top: 4px; }
        h1 { text-align: center; font-size: 16px; letter-spacing: 2px; margin: 18px 0 4px; }
        .numero { text-align: center; font-size: 10px; color: #5B6779; margin-bottom: 22px; }
        p { text-align: justify; margin: 0 0 12px; }
        table.notas { width: 100%; border-collapse: collapse; margin: 6px 0 14px; font-family: DejaVu Sans, sans-serif; font-size: 9.5px; }
        table.notas th, table.notas td { border: 1px solid #A9B4C2; padding: 3px 5px; }
        table.notas th { background: #E6EDF5; }
        .c { text-align: center; }
        .assinatura { margin-top: 50px; text-align: center; }
        .assinatura .linha { border-top: 1px solid #1B2533; display: inline-block; padding: 3px 60px 0; }
        .verificacao { position: fixed; bottom: -30px; left: 0; right: 0; font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #5B6779; border-top: 1px solid #E1E6ED; padding-top: 4px; }
        .demo { position: fixed; top: 40%; left: 5%; font-size: 48px; color: rgba(183, 121, 31, .18); transform: rotate(-30deg); font-family: DejaVu Sans, sans-serif; font-weight: bold; }
    </style>
</head>
<body>
    @if (config('sige.modo_demonstracao'))<div class="demo">DEMONSTRAÇÃO</div>@endif
    @php
        $a = $c['aluno']; $m = $c['matricula']; $e = $c['escola'];
        $o = $a['genero'] === 'F' ? 'a' : 'o';
        $titulo = ['matricula' => 'DECLARAÇÃO DE MATRÍCULA', 'frequencia' => 'DECLARAÇÃO DE FREQUÊNCIA', 'notas' => 'DECLARAÇÃO COM NOTAS'][$d->tipo];
        $frequenta = $m['estado'] === 'Activa' ? 'frequenta' : 'frequentou';
    @endphp

    <div class="topo">
        <div class="rep">República de Angola</div>
        <div class="rep">Província do {{ $e['provincia'] }} · Município do {{ $e['municipio'] }}</div>
        <div class="escola">{{ $e['nome'] }}</div>
        <div style="font-size:9.5px">Código da instituição: {{ $e['codigo'] }}{{ $e['telefone'] ? ' · Tel. '.$e['telefone'] : '' }}</div>
    </div>

    <h1>{{ $titulo }}</h1>
    <div class="numero">N.º {{ $d->numero }}</div>

    <p>Para os devidos efeitos, declara-se que <strong>{{ $a['nome'] }}</strong>, de nacionalidade {{ mb_strtolower($a['nacionalidade']) }},
        nascid{{ $o }} a {{ $a['nascimento'] }}{{ $a['naturalidade'] ? ', natural de '.$a['naturalidade'] : '' }}{{ $a['documento'] ? ', portador'.($a['genero'] === 'F' ? 'a' : '').' do '.$a['documento'] : '' }},
        com o processo individual n.º {{ $a['processo'] }},
        @if ($d->tipo === 'matricula')
            se encontra matriculad{{ $o }} nesta instituição no ano lectivo de <strong>{{ $m['ano_lectivo'] }}</strong>, na <strong>{{ $m['classe'] }}</strong>
            ({{ $m['nivel'] }}){{ $m['curso'] ? ', curso de '.$m['curso'] : '' }}{{ $m['turma'] ? ', turma '.$m['turma'] : '' }}{{ $m['turno'] ? ', turno da '.mb_strtolower($m['turno']) : '' }},
            com a matrícula n.º {{ $m['numero'] }}, efectuada a {{ $m['data'] }}.
        @elseif ($d->tipo === 'frequencia')
            {{ $frequenta }} nesta instituição, no ano lectivo de <strong>{{ $m['ano_lectivo'] }}</strong>, a <strong>{{ $m['classe'] }}</strong>
            ({{ $m['nivel'] }}){{ $m['curso'] ? ', curso de '.$m['curso'] : '' }}{{ $m['turma'] ? ', turma '.$m['turma'] : '' }}.
            @if (($c['frequencia']['percentagem'] ?? null) !== null)
                À data da emissão, regista uma frequência de {{ number_format($c['frequencia']['percentagem'], 1, ',', '') }}% das aulas previstas
                ({{ $c['frequencia']['faltas'] }} tempo(s) em falta, dos quais {{ $c['frequencia']['justificadas'] }} justificado(s)).
            @endif
        @else
            {{ $frequenta }} nesta instituição, no ano lectivo de <strong>{{ $m['ano_lectivo'] }}</strong>, a <strong>{{ $m['classe'] }}</strong>
            ({{ $m['nivel'] }}){{ $m['curso'] ? ', curso de '.$m['curso'] : '' }}{{ $m['turma'] ? ', turma '.$m['turma'] : '' }}, tendo obtido as seguintes classificações:
        @endif
    </p>

    @if ($d->tipo === 'notas')
        <table class="notas">
            <thead><tr><th>Disciplina</th><th class="c">I Trim.</th><th class="c">II Trim.</th><th class="c">III Trim.</th><th class="c">Classif. final</th></tr></thead>
            <tbody>@foreach ($c['notas'] as $n)<tr><td>{{ $n['disciplina'] }}</td><td class="c">{{ $n['t1'] }}</td><td class="c">{{ $n['t2'] }}</td><td class="c">{{ $n['t3'] }}</td><td class="c"><strong>{{ $n['final'] }}</strong></td></tr>@endforeach</tbody>
        </table>
        @if ($c['resultado'])<p>Resultado final: <strong>{{ $c['resultado']['rotulo'] }}</strong>.</p>@endif
    @endif

    <p>{{ $d->finalidade ? 'A presente declaração destina-se '.(str_starts_with(mb_strtolower($d->finalidade), 'para') ? '' : 'a ').$d->finalidade.'.' : 'Por ser verdade e me ter sido solicitada, passa-se a presente declaração.' }}</p>

    <p>{{ $e['municipio'] }}, aos {{ $d->created_at->translatedFormat('d \d\e F \d\e Y') }}.</p>

    @php $director = $e['director_nome'] && ! str_contains($e['director_nome'], 'demonstrativo') ? $e['director_nome'] : null; @endphp
    <div class="assinatura">
        <div style="font-size:10px; margin-bottom: 34px">O(A) Director(a) da Escola</div>
        <div class="linha">{{ $director ?? '' }}&nbsp;</div>
    </div>

    <div class="verificacao">
        Documento n.º {{ $d->numero }} · Código de verificação: <strong>{{ $d->codigo_verificacao }}</strong> · Emitido em {{ $d->created_at->format('d/m/Y H:i') }}
        por {{ $d->emitidaPor?->nome ?? '—' }} através do {{ config('sige.nome_completo') }}.
        @if (config('sige.modo_demonstracao')) DOCUMENTO DE DEMONSTRAÇÃO — dados fictícios, sem validade. @endif
    </div>
</body>
</html>
