<x-app-layout :titulo="'Versão '.$registo->versao">
    @php $s = $registo->snapshot; @endphp
    <x-cabecalho :titulo="$config->nome.' — versão '.$registo->versao"
        :subtitulo="ucfirst($registo->accao).' em '.$registo->created_at->format('d/m/Y H:i').' por '.($registo->utilizador?->nome ?? 'Sistema')"
        :migalhas="['Configuração de Avaliações' => route('avaliacao.configuracoes.index'), $config->nome => route('avaliacao.configuracoes.show', $config), 'Versão '.$registo->versao => null]" />

    @if ($registo->motivo)<div class="alert alert-light border"><strong>Motivo:</strong> {{ $registo->motivo }}</div>@endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-header"><h2 class="titulo">Campos</h2></div>
                <div class="card-body"><dl class="lista-detalhes">
                    @foreach ($s['campos'] ?? [] as $k => $v)<dt>{{ str_replace('_', ' ', $k) }}</dt><dd>{{ is_scalar($v) || $v === null ? ($v ?? '—') : json_encode($v) }}</dd>@endforeach
                </dl></div></div>
            <div class="card"><div class="card-header"><h2 class="titulo">Componentes</h2></div>
                <ul class="list-group list-group-flush">@foreach ($s['componentes'] ?? [] as $c)<li class="list-group-item small"><code>{{ $c['codigo'] }}</code> {{ $c['nome'] }} · {{ $c['tipo'] }} · {{ $c['momento'] }} {{ $c['trimestres'] ? '('.$c['trimestres'].')' : '' }} · {{ $c['nota_minima'] }}–{{ $c['nota_maxima'] }}</li>@endforeach</ul></div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-header"><h2 class="titulo">Fórmulas</h2></div>
                <ul class="list-group list-group-flush">@foreach ($s['formulas'] ?? [] as $f)<li class="list-group-item small"><code>{{ $f['codigo'] }} = {{ $f['expressao'] }}</code> · {{ $f['nivel'] }} {{ $f['trimestres'] ? '('.$f['trimestres'].')' : '' }} {{ ! empty($f['arredondar']) ? '· arredondada' : '' }}</li>@endforeach</ul></div>
            <div class="card"><div class="card-header"><h2 class="titulo">Regras</h2></div>
                <ul class="list-group list-group-flush">@foreach ($s['regras'] ?? [] as $r)<li class="list-group-item small"><strong>{{ config('avaliacao.regras.'.$r['tipo'].'.nome', $r['tipo']) }}</strong> {{ empty($r['activo']) ? '(inactiva)' : '' }}<br><code class="small">{{ json_encode($r['parametros'], JSON_UNESCAPED_UNICODE) }}</code></li>@endforeach</ul></div>
        </div>
    </div>
</x-app-layout>
