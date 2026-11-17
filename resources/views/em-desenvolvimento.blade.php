<x-app-layout :titulo="$item['rotulo']">
    <x-cabecalho :titulo="$item['rotulo']" :migalhas="[$item['seccao'] => null, $item['rotulo'] => null]" />

    <div class="card">
        <div class="card-body">
            <div class="bloco-desenvolvimento">
                <div class="icone tom-alerta"><i class="bi bi-{{ $item['icone'] }}"></i></div>
                <span class="badge text-bg-warning mb-2">Funcionalidade em desenvolvimento · {{ $item['em_desenvolvimento'] }}</span>
                <h2 class="h5 fw-semibold">{{ $item['rotulo'] }}</h2>
                <p class="text-secondary mb-4">
                    Este módulo faz parte do plano de implementação por fases e ainda não está operacional.
                    Nenhuma informação apresentada nesta área é simulada.
                </p>
                <a href="{{ route('inicio') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Voltar ao início</a>
            </div>
        </div>
    </div>
</x-app-layout>
