@extends('avaliacao.pautas._pdf-base')
@section('conteudo')
    @include('avaliacao.pautas._tabela-turma', ['modo' => 'pdf'])
    @if ($tipo === 'final' && $situacoes->isNotEmpty())
        <p class="small" style="margin-top:6px">
            <strong>Resumo:</strong>
            @foreach ($situacoes as $s => $n){{ config("avaliacao.situacoes.$s.rotulo") }}: {{ $n }}@if (! $loop->last) · @endif @endforeach
        </p>
    @endif
    <p class="small">Legenda: @foreach ($disciplinas as $d){{ $d->sigla }} — {{ $d->nome }}@if (! $loop->last); @endif @endforeach</p>
@endsection
