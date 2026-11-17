<x-app-layout :titulo="'Painel Municipal — '.$municipio->nome">
    <x-cabecalho :titulo="'Painel Municipal — '.$municipio->nome" subtitulo="Escolas, alunos, professores e resultados do município"
        :migalhas="['Painéis' => null, 'Painel Municipal' => null]">
        @can('mapa.ver')<a href="{{ route('mapa', ['municipio_id' => $municipio->id]) }}" class="btn btn-light"><i class="bi bi-geo-alt me-1"></i>Mapa</a>@endcan
        <a href="{{ route('relatorios.index') }}" class="btn btn-light"><i class="bi bi-file-earmark-bar-graph me-1"></i>Relatórios</a>
    </x-cabecalho>

    @include('paineis._filtros', ['fixarMunicipio' => ! $escolherMunicipio, 'municipioObrigatorio' => true])
    @include('paineis._rede', ['modo' => 'municipal'])
</x-app-layout>
