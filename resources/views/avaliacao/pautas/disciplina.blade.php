<x-app-layout :titulo="$disciplina->nome.' — '.$turma->designacao">
    <x-cabecalho :titulo="$disciplina->nome.' — '.$turma->designacao" :subtitulo="$escola->nome.' · '.$turma->anoLectivo->designacao"
        :migalhas="['Avaliação' => null, 'Pautas' => route('avaliacao.pautas.index'), $turma->designacao => route('avaliacao.pautas.turma', [$turma, 'periodo' => 0]), $disciplina->sigla => null]">
        @if (auth()->user()->can('notas.lancar') || auth()->user()->can('notas.ver'))
            <a href="{{ route('avaliacao.lancamento.grelha', [$turma, $disciplina->id]) }}" class="btn btn-light"><i class="bi bi-pencil-square me-1"></i>Grelha de notas</a>
        @endif
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf']) }}" class="btn btn-light"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'xlsx']) }}" class="btn btn-light"><i class="bi bi-file-earmark-excel me-1 text-success"></i>Excel</a>
    </x-cabecalho>

    @if (! $config)
        <div class="alert alert-warning">Sem configuração de avaliação activa.</div>
    @else
        @if ($desactualizados)
            <div class="alert alert-warning small">{{ $desactualizados }} resultado(s) calculados com versão anterior da configuração.</div>
        @endif
        <div class="card">
            <div class="table-responsive">@include('avaliacao.pautas._tabela-disciplina', ['modo' => 'html'])</div>
            <div class="card-body small text-secondary border-top">
                Fórmulas ({{ $config->nome }}, v{{ $config->versao }}):
                @foreach ($config->formulas as $f)<code class="ms-1">{{ $f->codigo }} = {{ $f->expressao }}</code>@endforeach
            </div>
        </div>
    @endif

    @push('estilos')
        <style>.tabela-pauta th, .tabela-pauta td { font-size: .78rem; white-space: nowrap; } .tabela-pauta td.negativa { color: var(--sige-erro); }</style>
    @endpush
</x-app-layout>
