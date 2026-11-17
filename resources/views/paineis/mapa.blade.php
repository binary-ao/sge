<x-app-layout titulo="Mapa da rede escolar">
    <x-cabecalho titulo="Mapa da rede escolar" subtitulo="Localização das instituições de ensino da província do Bengo"
        :migalhas="['Painéis' => null, 'Mapa da Rede' => null]" />

    <form method="GET" class="barra-filtros row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Município</label>
            <select name="municipio_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todos</option>
                @foreach ($municipios as $m)<option value="{{ $m->id }}" @selected($filtros->municipioId == $m->id)>{{ $m->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Tipologia</label>
            <select name="tipologia_id" class="form-select form-select-sm" data-auto-submit>
                <option value="">Todas</option>
                @foreach ($tipologias as $t)<option value="{{ $t->id }}" @selected($filtros->tipologiaId == $t->id)>{{ $t->nome }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 small text-secondary">{{ $pontos->count() }} instituição(ões) no mapa{{ $semCoordenadas ? ' · '.$semCoordenadas.' sem coordenadas' : '' }}</div>
    </form>

    <div class="alert alert-warning small py-2"><i class="bi bi-geo me-1"></i>
        As coordenadas das escolas de demonstração são <strong>aproximadas e demonstrativas</strong> (assinaladas no cadastro de cada escola). O fundo cartográfico (OpenStreetMap) exige ligação à Internet.
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card"><div id="mapa" style="height: 620px; border-radius: 8px;"></div></div>
            <div id="semMapa" class="small text-secondary mt-1" hidden>Não foi possível carregar o fundo cartográfico (sem ligação à Internet). As escolas continuam marcadas pelas coordenadas.</div>
        </div>
        <div class="col-xl-3">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Legenda</h2></div>
                <ul class="list-group list-group-flush small" id="legenda"></ul>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="titulo">Instituições</h2></div>
                <ul class="list-group list-group-flush small" style="max-height: 360px; overflow:auto" id="listaEscolas"></ul>
            </div>
        </div>
    </div>

    @push('estilos')
        <link rel="stylesheet" href="{{ asset('libs/leaflet/leaflet.css') }}">
        <style>.leaflet-popup-content { font-family: inherit; font-size: .82rem; } .ponto-legenda { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }</style>
    @endpush
    @push('scripts')
        <script src="{{ asset('libs/leaflet/leaflet.js') }}"></script>
        <script>
            (function () {
                var pontos = @json($pontos);
                var tipologias = @json($tipologias->pluck('nome', 'id'));
                var paleta = ['#0B2A4A', '#1D6FB8', '#6E9BC5', '#B7791F', '#1E7B4D', '#8A9BB0', '#14406B', '#C9A44C', '#4F7FA8', '#B42318'];
                var cor = function (id) { return paleta[Object.keys(tipologias).indexOf(String(id)) % paleta.length]; };
                var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

                var mapa = L.map('mapa', {scrollWheelZoom: true}).setView([-8.35, 14.0], 8);
                var camada = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 18, attribution: '&copy; OpenStreetMap'});
                camada.on('tileerror', function () { document.getElementById('semMapa').hidden = false; });
                camada.addTo(mapa);

                var limites = [];
                var marcadores = {};
                pontos.forEach(function (p) {
                    var m = L.circleMarker([p.lat, p.lng], {radius: 6 + Math.min(10, Math.sqrt(p.alunos) / 1.5), color: '#fff', weight: 2, fillColor: cor(p.tipologia_id), fillOpacity: .9}).addTo(mapa);
                    m.bindPopup('<strong>' + esc(p.nome) + '</strong><br>' + esc(p.municipio) + ' · ' + esc(p.natureza)
                        + '<br><span style="color:#5B6779">' + esc(p.tipologia) + '</span>'
                        + '<br>Níveis: ' + p.niveis.map(esc).join(', ')
                        + '<br>Alunos: <strong>' + p.alunos + '</strong>'
                        + (p.demo ? '<br><em style="color:#7A5300">Coordenadas demonstrativas</em>' : '')
                        + '<br><a href="' + p.url + '">Abrir ficha da escola</a>');
                    marcadores[p.id] = m;
                    limites.push([p.lat, p.lng]);
                });
                if (limites.length) { mapa.fitBounds(limites, {padding: [40, 40], maxZoom: 11}); }

                var usadas = {};
                pontos.forEach(function (p) { usadas[p.tipologia_id] = p.tipologia; });
                document.getElementById('legenda').innerHTML = Object.keys(usadas).map(function (id) {
                    return '<li class="list-group-item"><span class="ponto-legenda" style="background:' + cor(id) + '"></span>' + esc(usadas[id]) + '</li>';
                }).join('') + '<li class="list-group-item text-secondary">O tamanho do círculo é proporcional ao número de alunos.</li>';
                document.getElementById('listaEscolas').innerHTML = pontos.map(function (p) {
                    return '<li class="list-group-item list-group-item-action" role="button" data-id="' + p.id + '"><span class="ponto-legenda" style="background:' + cor(p.tipologia_id) + '"></span>'
                        + esc(p.nome) + '<br><span class="text-secondary ms-4">' + esc(p.municipio) + ' · ' + p.alunos + ' alunos</span></li>';
                }).join('') || '<li class="list-group-item text-secondary">Sem instituições para os filtros.</li>';
                document.getElementById('listaEscolas').addEventListener('click', function (e) {
                    var li = e.target.closest('[data-id]'); if (!li) { return; }
                    var m = marcadores[li.dataset.id]; mapa.setView(m.getLatLng(), 12); m.openPopup();
                });
            })();
        </script>
    @endpush
</x-app-layout>
