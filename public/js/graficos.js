/* SIGE Bengo — gráficos dos painéis (Chart.js). Paleta institucional sóbria. */
window.SigeGraficos = (function () {
    'use strict';

    var PALETA = ['#0B2A4A', '#1D6FB8', '#6E9BC5', '#B7791F', '#1E7B4D', '#8A9BB0', '#14406B', '#C9A44C', '#4F7FA8', '#A7B8CC'];
    var VERDE = '#1E7B4D', VERMELHO = '#B42318', AMBAR = '#B7791F', CINZA = '#B5C0CE';

    function base() {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#5B6779';
        Chart.defaults.plugins.tooltip.backgroundColor = '#0B2A4A';
        Chart.defaults.plugins.tooltip.padding = 10;
    }

    function numero(v, sufixo) {
        if (v === null || v === undefined) { return '—'; }
        return (Math.round(v * 10) / 10).toLocaleString('pt-PT') + (sufixo || '');
    }

    function cursor(e, itens, clicavel) {
        e.native.target.style.cursor = itens.length && clicavel ? 'pointer' : 'default';
    }

    /** Barras simples. dados: [{nome, total, id}], opcoes: {horizontal, cor, sufixo, max, onClick(item)} */
    function barras(id, dados, opcoes) {
        var el = document.getElementById(id); if (!el || !dados.length) { return null; }
        opcoes = opcoes || {};
        var horizontal = opcoes.horizontal !== false;
        var valores = dados.map(function (d) { return d.total; });
        var cores = opcoes.corPorValor ? valores.map(opcoes.corPorValor) : (opcoes.cor || '#14406B');
        var eixoValor = {beginAtZero: true, max: opcoes.max, ticks: {precision: 0, callback: function (v) { return numero(v, opcoes.sufixo); }}, grid: {color: '#EEF1F5'}};
        var eixoCat = {grid: {display: false}, ticks: {autoSkip: false, callback: function (v) { var l = this.getLabelForValue(v); return l.length > 34 ? l.slice(0, 32) + '…' : l; }}};
        return new Chart(el, {
            type: 'bar',
            data: {labels: dados.map(function (d) { return d.nome; }), datasets: [{data: valores, backgroundColor: cores, borderRadius: 4, maxBarThickness: 26}]},
            options: {
                indexAxis: horizontal ? 'y' : 'x', maintainAspectRatio: false,
                plugins: {legend: {display: false}, tooltip: {callbacks: {label: function (c) { return numero(c.raw, opcoes.sufixo) + (opcoes.legenda ? ' ' + opcoes.legenda : ''); }}}},
                scales: horizontal ? {x: eixoValor, y: eixoCat} : {y: eixoValor, x: eixoCat},
                onClick: function (e, itens) { if (itens.length && opcoes.onClick) { opcoes.onClick(dados[itens[0].index]); } },
                onHover: function (e, itens) { cursor(e, itens, !!opcoes.onClick); }
            }
        });
    }

    /** Circular. dados: [{nome, total, id}] */
    function circular(id, dados, opcoes) {
        var el = document.getElementById(id); if (!el || !dados.length) { return null; }
        opcoes = opcoes || {};
        return new Chart(el, {
            type: 'doughnut',
            data: {labels: dados.map(function (d) { return d.nome; }), datasets: [{data: dados.map(function (d) { return d.total; }), backgroundColor: opcoes.cores || PALETA, borderColor: '#fff', borderWidth: 2}]},
            options: {
                maintainAspectRatio: false, cutout: '62%',
                plugins: {legend: {position: 'bottom', labels: {boxWidth: 12}}},
                onClick: function (e, itens) { if (itens.length && opcoes.onClick) { opcoes.onClick(dados[itens[0].index]); } },
                onHover: function (e, itens) { cursor(e, itens, !!opcoes.onClick); }
            }
        });
    }

    /** Barras agrupadas/empilhadas. series: [{rotulo, valores[], cor}] */
    function series(id, rotulos, series, opcoes) {
        var el = document.getElementById(id); if (!el || !rotulos.length) { return null; }
        opcoes = opcoes || {};
        return new Chart(el, {
            type: 'bar',
            data: {labels: rotulos, datasets: series.map(function (s, i) {
                return {label: s.rotulo, data: s.valores, backgroundColor: s.cor || PALETA[i % PALETA.length], borderRadius: 3, maxBarThickness: 34};
            })},
            options: {
                maintainAspectRatio: false, indexAxis: opcoes.horizontal ? 'y' : 'x',
                plugins: {legend: {position: 'bottom', labels: {boxWidth: 12}},
                    tooltip: {callbacks: {label: function (c) { return c.dataset.label + ': ' + numero(c.raw, opcoes.sufixo); }}}},
                scales: {
                    x: {stacked: !!opcoes.empilhado, grid: {display: !!opcoes.horizontal, color: '#EEF1F5'}, max: opcoes.horizontal ? opcoes.max : undefined,
                        ticks: opcoes.horizontal ? {callback: function (v) { return numero(v, opcoes.sufixo); }} : {}},
                    y: {stacked: !!opcoes.empilhado, beginAtZero: true, max: opcoes.horizontal ? undefined : opcoes.max, grid: {color: '#EEF1F5', display: !opcoes.horizontal},
                        ticks: {callback: function (v) { return opcoes.horizontal ? this.getLabelForValue(v) : numero(v, opcoes.sufixo); }}}
                }
            }
        });
    }

    /** Cor da barra de taxa de aprovação. */
    function corTaxa(v) { return v >= 90 ? VERDE : (v >= 75 ? '#4F9A6E' : (v >= 60 ? AMBAR : VERMELHO)); }

    /** Navega para um URL com parâmetros adicionais (mantendo os filtros actuais). */
    function ir(url, params) {
        var u = new URL(url, window.location.origin);
        Object.keys(params || {}).forEach(function (k) { if (params[k] !== null && params[k] !== undefined) { u.searchParams.set(k, params[k]); } });
        window.location = u.toString();
    }

    document.addEventListener('DOMContentLoaded', function () { if (window.Chart) { base(); } });

    return {barras: barras, circular: circular, series: series, corTaxa: corTaxa, ir: ir, PALETA: PALETA, VERDE: VERDE, VERMELHO: VERMELHO, AMBAR: AMBAR, CINZA: CINZA, numero: numero};
})();
