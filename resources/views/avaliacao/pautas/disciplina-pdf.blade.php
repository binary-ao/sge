@extends('avaliacao.pautas._pdf-base')
@section('conteudo')
    @if ($config)
        @include('avaliacao.pautas._tabela-disciplina', ['modo' => 'pdf'])
        <p class="small">Fórmulas: @foreach ($config->formulas as $f){{ $f->codigo }} = {{ $f->expressao }}@if (! $loop->last); @endif @endforeach</p>
    @else
        <p>Sem configuração de avaliação activa.</p>
    @endif
@endsection
