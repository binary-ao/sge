@props(['titulo', 'subtitulo' => null, 'migalhas' => []])
{{-- Cabeçalho de página com breadcrumbs. $migalhas = ['Rótulo' => url|null, …] --}}
<div class="cabecalho-pagina">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('inicio') }}"><i class="bi bi-house-door"></i> Início</a></li>
                @foreach ($migalhas as $rotulo => $url)
                    @if ($url && ! $loop->last)
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1>{{ $titulo }}</h1>
        @if ($subtitulo)
            <div class="subtitulo">{{ $subtitulo }}</div>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
