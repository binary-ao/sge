<x-app-layout :titulo="$titulo.' — '.$turma->designacao">
    <x-cabecalho :titulo="$titulo.' — '.$turma->designacao"
        :subtitulo="$escola->nome.' · '.$turma->anoLectivo->designacao.' · '.$turma->classe->nivelEnsino->nome.($turma->curso ? ' · '.$turma->curso->nome : '')"
        :migalhas="['Avaliação' => null, 'Pautas' => route('avaliacao.pautas.index', ['ano_lectivo_id' => $turma->ano_lectivo_id]), $turma->designacao => null]">
        <div class="btn-group">
            @foreach ([1 => 'I Trim.', 2 => 'II Trim.', 3 => 'III Trim.', 0 => 'Final'] as $p => $r)
                <a href="{{ route('avaliacao.pautas.turma', [$turma, 'periodo' => $p]) }}" class="btn btn-sm {{ $periodo === $p ? 'btn-primary' : 'btn-light' }}">{{ $r }}</a>
            @endforeach
        </div>
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf']) }}" class="btn btn-light"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'xlsx']) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1 text-success"></i>Excel</a>
    </x-cabecalho>

    @if (! $config)
        <div class="alert alert-warning">Não existe configuração de avaliação activa para esta classe neste ano lectivo.</div>
    @endif
    @if ($desactualizados)
        <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>
            {{ $desactualizados }} resultado(s) foram calculados com uma versão anterior da configuração ({{ $config->nome }}, actualmente v{{ $config->versao }}).
            Os resultados não são alterados retroactivamente; @can('pautas.fechar')utilize o <a href="{{ route('avaliacao.periodos.index') }}">recálculo controlado</a> se pretender aplicar a versão actual.@else contacte a direcção da escola.@endcan
        </div>
    @endif
    @if ($fechado === false && $periodo > 0)
        <div class="alert alert-light border small py-2"><i class="bi bi-unlock me-1"></i>Período ainda aberto: a pauta é provisória.</div>
    @endif

    @if ($tipo === 'final' && $situacoes->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach ($situacoes as $s => $n)
                <span class="badge text-bg-{{ config("avaliacao.situacoes.$s.cor") }} fs-6 fw-normal">{{ config("avaliacao.situacoes.$s.rotulo") }}: {{ $n }}</span>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            @include('avaliacao.pautas._tabela-turma', ['modo' => 'html'])
        </div>
        <div class="card-body small text-secondary border-top d-flex flex-wrap gap-3">
            @foreach ($disciplinas as $d)
                <span><strong>{{ $d->sigla }}</strong> — <a href="{{ route('avaliacao.pautas.disciplina', [$turma, $d->id]) }}">{{ $d->nome }}</a></span>
            @endforeach
        </div>
    </div>
    <p class="small text-secondary mt-2">
        @if ($config)Configuração: {{ $config->nome }} (versão {{ $config->versao }}) · @endif
        Gerada em {{ $gerado_em->format('d/m/Y H:i') }}.
        @if ($tipo === 'final') Passe o cursor sobre o resultado para ver a justificação. @endif
    </p>

    @push('estilos')
        <style>
            .tabela-pauta th, .tabela-pauta td { font-size: .8rem; white-space: nowrap; }
            .tabela-pauta td.negativa { color: var(--sige-erro); font-weight: 600; }
            .tabela-pauta thead th { text-align: center; }
        </style>
    @endpush
</x-app-layout>
