@props(['rotulo', 'valor', 'icone' => 'bar-chart', 'tom' => 'azul', 'detalhe' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="cartao-estatistico {{ $href ? 'text-decoration-none' : '' }}">
    <div class="icone tom-{{ $tom }}"><i class="bi bi-{{ $icone }}"></i></div>
    <div class="min-w-0">
        <div class="valor">{{ $valor }}</div>
        <div class="rotulo">{{ $rotulo }}</div>
        @if ($detalhe)
            <div class="small text-secondary mt-1">{{ $detalhe }}</div>
        @endif
    </div>
</{{ $tag }}>
