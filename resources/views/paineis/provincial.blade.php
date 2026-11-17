<x-app-layout titulo="Painel Provincial">
    <x-cabecalho titulo="Painel Provincial" subtitulo="Visão consolidada da educação na província do Bengo"
        :migalhas="['Painéis' => null, 'Painel Provincial' => null]">
        @can('mapa.ver')<a href="{{ route('mapa', $filtros->paraUrl()) }}" class="btn btn-light"><i class="bi bi-geo-alt me-1"></i>Mapa da rede</a>@endcan
        <a href="{{ route('relatorios.index') }}" class="btn btn-light"><i class="bi bi-file-earmark-bar-graph me-1"></i>Relatórios</a>
    </x-cabecalho>

    @include('paineis._filtros')
    @include('paineis._rede', ['modo' => 'provincial'])
</x-app-layout>
