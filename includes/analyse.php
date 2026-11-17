<?php
require_once __DIR__ . '/lots.php';

const CATEGORIES_DEPENSES = [
    'aliment'     => 'Aliment',
    'poussins'    => 'Poussins / poulettes',
    'sante'       => 'Santé',
    'main_oeuvre' => "Main-d'œuvre",
    'energie'     => 'Énergie',
    'autre'       => 'Autre',
];

/**
 * Séries quotidiennes d'un lot, prêtes pour les graphiques.
 * Les effectifs vivants sont recalculés jour après jour à partir de la mortalité saisie.
 */
function lot_serie(array $lot): array {
    $st = db()->prepare(
        'SELECT date_suivi, mortalite, aliment_kg, oeufs_produits, poids_moyen_g
         FROM suivis_quotidiens WHERE lot_id = ? ORDER BY date_suivi'
    );
    $st->execute([$lot['id']]);

    $s = [
        'dates' => [], 'dates_iso' => [], 'morts' => [], 'mort_cum_pct' => [], 'vivants' => [],
        'aliment_kg' => [], 'aliment_g_sujet' => [], 'taux_ponte' => [], 'oeufs' => [], 'poids' => [],
    ];
    $initial = max(1, (int)$lot['effectif_initial']);
    $vivants = $initial;
    $cum = 0;

    foreach ($st->fetchAll() as $r) {
        $m = (int)$r['mortalite'];
        $vivants = max(0, $vivants - $m);
        $cum += $m;
        $alim = (float)$r['aliment_kg'];

        $s['dates'][]           = date('d/m', strtotime($r['date_suivi']));
        $s['dates_iso'][]       = $r['date_suivi'];
        $s['morts'][]           = $m;
        $s['mort_cum_pct'][]    = round($cum / $initial * 100, 2);
        $s['vivants'][]         = $vivants;
        $s['aliment_kg'][]      = round($alim, 2);
        $s['aliment_g_sujet'][] = $vivants > 0 ? round($alim * 1000 / $vivants, 1) : null;
        $s['oeufs'][]           = $r['oeufs_produits'] !== null ? (int)$r['oeufs_produits'] : null;
        $s['taux_ponte'][]      = ($r['oeufs_produits'] !== null && $vivants > 0)
                                    ? round((int)$r['oeufs_produits'] / $vivants * 100, 1) : null;
        $s['poids'][]           = $r['poids_moyen_g'] !== null ? (int)$r['poids_moyen_g'] : null;
    }
    return $s;
}

/** Dépenses (par catégorie), ventes et marge d'un lot */
function lot_finances(array $lot): array {
    $pdo = db();

    $dep = array_fill_keys(array_keys(CATEGORIES_DEPENSES), 0);
    $st = $pdo->prepare('SELECT categorie, SUM(montant_fcfa) AS t FROM depenses WHERE lot_id = ? GROUP BY categorie');
    $st->execute([$lot['id']]);
    foreach ($st->fetchAll() as $r) {
        $dep[$r['categorie']] = (int)$r['t'];
    }

    $st = $pdo->prepare('SELECT COALESCE(SUM(montant_fcfa), 0) FROM ventes WHERE lot_id = ?');
    $st->execute([$lot['id']]);
    $ventes = (int)$st->fetchColumn();

    $totalDep = array_sum($dep);
    return [
        'depenses'       => $dep,
        'total_depenses' => $totalDep,
        'ventes'         => $ventes,
        'marge'          => $ventes - $totalDep,
    ];
}

/** Indicateurs clés à afficher : liste de [valeur, libellé] */
function lot_indicateurs(array $lot, array $s, array $fin): array {
    $moy = function (array $v): ?float {
        $v = array_values(array_filter($v, fn($x) => $x !== null));
        return $v ? array_sum($v) / count($v) : null;
    };
    $dernier7 = fn(array $v) => array_slice($v, -7);
    $f = fn(?float $x, int $d = 1) => $x === null ? '—' : number_format($x, $d, ',', ' ');

    $conso7 = $moy($dernier7($s['aliment_g_sujet']));
    $out = [];

    if ($lot['type_production'] === 'pondeuse') {
        $plateaux = array_sum($s['oeufs']) / 30;
        $out[] = [$f($moy($dernier7($s['taux_ponte']))) . ' %', 'Taux de ponte (7 derniers jours)'];
        $out[] = [$f($conso7) . ' g', 'Aliment / sujet / jour (7 j)'];
        $out[] = [number_format($plateaux, 0, ',', ' '), 'Plateaux produits (30 œufs)'];
        $out[] = [$plateaux > 0 ? fcfa($fin['depenses']['aliment'] / $plateaux) : '—', "Coût d'aliment par plateau"];
    } else {
        // dernière pesée et indice de consommation (aliment consommé / poids vif produit)
        $idx = null;
        foreach ($s['poids'] as $i => $p) {
            if ($p !== null) $idx = $i;
        }
        $ic = null;
        $poids = null;
        if ($idx !== null) {
            $poids = $s['poids'][$idx];
            $alimCum = array_sum(array_slice($s['aliment_kg'], 0, $idx + 1));
            $poidsVifKg = $s['vivants'][$idx] * $poids / 1000;
            $ic = $poidsVifKg > 0 ? $alimCum / $poidsVifKg : null;
        }
        $out[] = [$poids === null ? '—' : number_format($poids, 0, ',', ' ') . ' g', 'Dernier poids moyen'];
        $out[] = [$f($ic, 2), 'Indice de consommation'];
        $out[] = [$f($conso7) . ' g', 'Aliment / sujet / jour (7 j)'];
        $out[] = [(string)count($s['dates']), 'Jours de suivi'];
    }
    return $out;
}
