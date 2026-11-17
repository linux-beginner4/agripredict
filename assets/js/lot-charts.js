/* AgriPredict CI — graphiques de la fiche lot (Chart.js, fonctionne hors ligne) */
(function () {
    'use strict';
    if (typeof Chart === 'undefined' || !window.LOT_DATA) return;

    var D = window.LOT_DATA;
    var S = D.serie;
    var P = (D.prevision && D.prevision.disponible) ? D.prevision : null;
    var VERT = '#2e7d32', ORANGE = '#ef6c00', ROUGE = '#c62828', GRIS = '#607d8b';

    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.color = '#455a64';

    function fr(n, d) {
        if (n === null || n === undefined) return '—';
        return Number(n).toLocaleString('fr-FR', { maximumFractionDigits: d === undefined ? 1 : d });
    }

    function pad(k) {
        var a = [];
        for (var i = 0; i < k; i++) a.push(null);
        return a;
    }

    function options(unit, extra) {
        var o = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            var v = c.chart.options.indexAxis !== 'y' ? c.parsed.y : c.parsed.x;
                            var nom = c.dataset.label ? c.dataset.label + ' : ' : '';
                            return nom + fr(v) + (unit ? ' ' + unit : '');
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 8, autoSkip: true } },
                y: { beginAtZero: true }
            }
        };
        if (extra && extra.scales) {
            Object.keys(extra.scales).forEach(function (k) {
                o.scales[k] = Object.assign({}, o.scales[k], extra.scales[k]);
            });
        }
        return o;
    }

    function canvas(id) { return document.getElementById(id); }

    // 1. Taux de ponte (pondeuses) ou poids moyen (poulets de chair), avec prévision
    var c1 = canvas('c-principal');
    if (c1) {
        var pondeuse = D.type === 'pondeuse';
        var histo = pondeuse ? S.taux_ponte : S.poids;
        var n = histo.length;
        var f = P ? P.dates.length : 0;
        var labels = P ? S.dates.concat(P.dates) : S.dates;

        var datasets = [{
            label: 'Historique',
            data: histo.concat(pad(f)),
            borderColor: VERT,
            backgroundColor: pondeuse ? 'rgba(46,125,50,.12)' : VERT,
            fill: pondeuse, tension: .25, pointRadius: pondeuse ? 0 : 4, borderWidth: 2, spanGaps: true
        }];

        if (P && P.valeurs.length) {
            var dernierIdx = n - 1;
            while (dernierIdx >= 0 && histo[dernierIdx] === null) dernierIdx--;

            var prev = pad(n + f);
            if (dernierIdx >= 0) prev[dernierIdx] = histo[dernierIdx];
            P.valeurs.forEach(function (x, i) { prev[n + i] = x; });
            datasets.push({
                label: 'Prévision', data: prev, borderColor: ORANGE, borderDash: [6, 4],
                tension: .25, pointRadius: pondeuse ? 0 : 3, borderWidth: 2, spanGaps: true, fill: false
            });

            if (P.bas && P.haut) {
                var bas = pad(n + f), haut = pad(n + f);
                if (dernierIdx >= 0) { bas[dernierIdx] = histo[dernierIdx]; haut[dernierIdx] = histo[dernierIdx]; }
                P.bas.forEach(function (x, i) { bas[n + i] = x; });
                P.haut.forEach(function (x, i) { haut[n + i] = x; });
                datasets.push({ label: 'Borne basse', data: bas, borderWidth: 0, pointRadius: 0, fill: false, spanGaps: true });
                datasets.push({
                    label: 'Borne haute', data: haut, borderWidth: 0, pointRadius: 0,
                    backgroundColor: 'rgba(239,108,0,.15)', fill: '-1', spanGaps: true
                });
            }
        }

        var o1 = options(pondeuse ? '%' : 'g', pondeuse ? { scales: { y: { max: 100 } } } : null);
        if (P) {
            o1.plugins.legend = { display: true, labels: { filter: function (it) { return it.datasetIndex < 2; } } };
            o1.plugins.tooltip.filter = function (it) { return it.datasetIndex < 2; };
        }
        new Chart(c1, { type: 'line', data: { labels: labels, datasets: datasets }, options: o1 });
    }

    // 2. Mortalité quotidienne
    var c2 = canvas('c-morts');
    if (c2) {
        new Chart(c2, {
            type: 'bar',
            data: { labels: S.dates, datasets: [{ data: S.morts, backgroundColor: ROUGE, borderRadius: 2 }] },
            options: options('mort(s)', { scales: { y: { ticks: { precision: 0 } } } })
        });
    }

    // 3. Aliment par sujet
    var c3 = canvas('c-aliment');
    if (c3) {
        new Chart(c3, {
            type: 'line',
            data: { labels: S.dates, datasets: [{
                data: S.aliment_g_sujet, borderColor: ORANGE, tension: .25,
                pointRadius: 0, borderWidth: 2, spanGaps: true
            }] },
            options: options('g / sujet')
        });
    }

    // 4. Dépenses par catégorie (barres horizontales)
    var c4 = canvas('c-depenses');
    if (c4 && D.depenses && D.depenses.labels.length) {
        var o4 = options('FCFA');
        o4.indexAxis = 'y';
        o4.scales = {
            x: { beginAtZero: true, ticks: { callback: function (v) { return fr(v, 0); } } },
            y: { grid: { display: false } }
        };
        new Chart(c4, {
            type: 'bar',
            data: { labels: D.depenses.labels, datasets: [{ data: D.depenses.valeurs, backgroundColor: GRIS, borderRadius: 3 }] },
            options: o4
        });
    }
})();
