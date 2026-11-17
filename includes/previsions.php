<?php
/**
 * Prévisions d'AgriPredict CI (estimations statistiques, sans modèle entraîné).
 *
 * Pondeuses : taux de ponte = médiane des 7 derniers jours (bande = ± 1,5 écart-type),
 *             effectif = mortalité moyenne des 28 derniers jours,
 *             aliment = médiane de la consommation par sujet.
 * Poulets de chair : poids = droite ajustée sur les 3 dernières pesées,
 *             aliment = tendance linéaire de la consommation par sujet (7 derniers jours).
 * Prix : dernier achat d'aliment (FCFA/kg) et dernière vente d'œufs (FCFA/plateau).
 */
require_once __DIR__ . '/analyse.php';

const PREV_HORIZON = 14;          // jours prévus
const PREV_POIDS_CIBLE_G = 2000;  // poids de vente visé pour les poulets de chair

/* ---------- Outils statistiques ---------- */

function prev_valeurs(array $v): array {
    return array_values(array_filter($v, fn($x) => $x !== null));
}

function prev_moyenne(array $v): ?float {
    $v = prev_valeurs($v);
    return $v ? array_sum($v) / count($v) : null;
}

function prev_mediane(array $v): ?float {
    $v = prev_valeurs($v);
    if (!$v) return null;
    sort($v);
    $n = count($v);
    return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
}

function prev_ecart_type(array $v): float {
    $v = prev_valeurs($v);
    $n = count($v);
    if ($n < 2) return 0.0;
    $m = array_sum($v) / $n;
    $s = 0.0;
    foreach ($v as $x) $s += ($x - $m) ** 2;
    return sqrt($s / ($n - 1));
}

/** Régression linéaire y = a + b x (moindres carrés) ; retourne [a, b] */
function prev_regression(array $x, array $y): array {
    $n = count($x);
    $mx = array_sum($x) / $n;
    $my = array_sum($y) / $n;
    $sxx = 0.0;
    $sxy = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $sxx += ($x[$i] - $mx) ** 2;
        $sxy += ($x[$i] - $mx) * ($y[$i] - $my);
    }
    $b = $sxx > 0 ? $sxy / $sxx : 0.0;
    return [$my - $b * $mx, $b];
}

/* ---------- Prix de référence ---------- */

/** Dernier prix d'achat de l'aliment (FCFA/kg) : montant de l'achat / kg consommés depuis l'achat précédent */
function prev_prix_aliment(array $lot): ?float {
    $pdo = db();
    $st = $pdo->prepare('SELECT date_suivi, aliment_kg FROM suivis_quotidiens WHERE lot_id = ? ORDER BY date_suivi');
    $st->execute([$lot['id']]);
    $suivis = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT date_depense, montant_fcfa FROM depenses
         WHERE lot_id = ? AND categorie = 'aliment' ORDER BY date_depense, id"
    );
    $st->execute([$lot['id']]);

    $prixFinal = null;
    $kgPrec = 0.0;
    foreach ($st->fetchAll() as $a) {
        $kgTot = 0.0;
        foreach ($suivis as $s) {
            if ($s['date_suivi'] <= $a['date_depense']) $kgTot += (float)$s['aliment_kg'];
        }
        $kg = $kgTot - $kgPrec;
        $kgPrec = $kgTot;
        if ($kg > 0) $prixFinal = (int)$a['montant_fcfa'] / $kg;
    }
    return $prixFinal;
}

/** Dernier prix de vente d'un plateau d'œufs (FCFA) */
function prev_prix_plateau(array $lot): ?int {
    $st = db()->prepare(
        "SELECT prix_unitaire_fcfa FROM ventes
         WHERE lot_id = ? AND produit = 'oeufs' ORDER BY date_vente DESC, id DESC LIMIT 1"
    );
    $st->execute([$lot['id']]);
    $p = $st->fetchColumn();
    return $p === false ? null : (int)$p;
}

/* ---------- Prévisions d'un lot ---------- */

function prev_fmt(?float $x, int $d = 0): string {
    return $x === null ? '—' : number_format($x, $d, ',', ' ');
}

function lot_previsions(array $lot, array $s, int $h = PREV_HORIZON): array {
    $res = [
        'disponible' => false, 'raison' => '', 'horizon' => $h,
        'dates' => [], 'valeurs' => [], 'bas' => null, 'haut' => null,
        'kpis' => [], 'methode' => '',
    ];

    if ($lot['statut'] === 'termine') {
        $res['raison'] = 'Ce lot est terminé : aucune prévision à calculer.';
        return $res;
    }

    $pondeuse = $lot['type_production'] === 'pondeuse';
    $n = count($s['dates']);
    $min = $pondeuse ? 14 : 7;
    if ($n < $min) {
        $res['raison'] = "Pas assez de données : $min jours de suivi minimum sont nécessaires ($n saisis).";
        return $res;
    }

    $iso   = $s['dates_iso'];
    $dernier = end($iso);
    $jours = array_map(fn($d) => (int)round((strtotime($d) - strtotime($iso[0])) / 86400), $iso);
    $jLast = end($jours);

    for ($k = 1; $k <= $h; $k++) {
        $res['dates'][] = date('d/m', strtotime("$dernier +$k days"));
    }

    $vivants0 = (int)end($s['vivants']);
    $m28 = array_slice($s['morts'], -28);
    $mortJour = $m28 ? array_sum($m28) / count($m28) : 0;
    $vivantsK = fn(int $k) => max(0, $vivants0 - $mortJour * $k);

    $prixKg = prev_prix_aliment($lot);

    if ($pondeuse) {
        $tauxs = prev_valeurs(array_slice($s['taux_ponte'], -7));
        $gs    = prev_mediane(array_slice($s['aliment_g_sujet'], -7));
        if (count($tauxs) < 5 || $gs === null) {
            $res['raison'] = "Saisissez le nombre d'œufs et l'aliment chaque jour : au moins 5 des 7 derniers jours sont nécessaires.";
            return $res;
        }
        $med = prev_mediane($tauxs);
        $sd  = prev_ecart_type($tauxs);

        $oeufs = 0.0;
        $kg = 0.0;
        $res['bas'] = $res['haut'] = [];
        for ($k = 1; $k <= $h; $k++) {
            $v = $vivantsK($k);
            $res['valeurs'][] = round($med, 1);
            $res['bas'][]     = round(max(0, $med - 1.5 * $sd), 1);
            $res['haut'][]    = round(min(100, $med + 1.5 * $sd), 1);
            $oeufs += $v * $med / 100;
            $kg    += $v * $gs / 1000;
        }

        $prixPlateau = prev_prix_plateau($lot);
        $plateaux = $oeufs / 30;
        $recettes = $prixPlateau !== null ? $plateaux * $prixPlateau : null;
        $budget   = $prixKg !== null ? $kg * $prixKg : null;
        $marge    = ($recettes !== null && $budget !== null) ? $recettes - $budget : null;

        $res['kpis'] = [
            [prev_fmt($oeufs / $h) . ' œufs', 'Production prévue par jour'],
            [$recettes === null ? '—' : prev_fmt($recettes) . ' FCFA', "Recettes estimées ($h j, " . prev_fmt($plateaux) . ' plateaux)'],
            [$budget === null ? '—' : prev_fmt($budget) . ' FCFA', "Budget aliment ($h j, " . prev_fmt($kg) . ' kg)'],
            [$marge === null ? '—' : prev_fmt($marge) . ' FCFA', "Marge sur aliment estimée ($h j)"],
        ];
        $res['methode'] = "Taux de ponte : médiane des 7 derniers jours, bande de ± 1,5 écart-type. "
            . "Effectif : mortalité moyenne des 28 derniers jours. Aliment : médiane de la consommation par sujet. "
            . "Prix : dernier achat d'aliment et dernière vente d'œufs du lot.";
    } else {
        // Poids : droite sur les 3 dernières pesées
        $pts = [];
        foreach ($s['poids'] as $i => $p) {
            if ($p !== null) $pts[] = [$jours[$i], $p];
        }
        $pts = array_slice($pts, -3);
        $poids7 = null;
        $dateCible = '—';
        if (count($pts) >= 2) {
            [$a, $b] = prev_regression(array_column($pts, 0), array_column($pts, 1));
            for ($k = 1; $k <= $h; $k++) {
                $res['valeurs'][] = (int)round(max(0, $a + $b * ($jLast + $k)));
            }
            $poids7 = $res['valeurs'][6] ?? null;
            $dernierPoids = end($pts)[1];
            if ($dernierPoids >= PREV_POIDS_CIBLE_G) {
                $dateCible = 'Déjà atteint';
            } elseif ($b > 0) {
                $joursRestants = (int)ceil((PREV_POIDS_CIBLE_G - $a) / $b - $jLast);
                $dateCible = $joursRestants <= 60 ? date('d/m', strtotime("$dernier +$joursRestants days")) : 'Au-delà de 60 j';
            }
        }

        // Aliment : tendance linéaire de la consommation par sujet
        $x = [];
        $y = [];
        foreach (array_slice(array_keys($s['aliment_g_sujet']), -7) as $i) {
            if ($s['aliment_g_sujet'][$i] !== null) {
                $x[] = $jours[$i];
                $y[] = $s['aliment_g_sujet'][$i];
            }
        }
        $kg = 0.0;
        if (count($x) >= 3) {
            [$a2, $b2] = prev_regression($x, $y);
            for ($k = 1; $k <= $h; $k++) {
                $kg += $vivantsK($k) * max(0, $a2 + $b2 * ($jLast + $k)) / 1000;
            }
        }
        $budget = ($prixKg !== null && $kg > 0) ? $kg * $prixKg : null;

        $res['kpis'] = [
            [$poids7 === null ? '—' : prev_fmt($poids7) . ' g', 'Poids moyen prévu dans 7 jours'],
            [$dateCible, 'Poids de ' . prev_fmt(PREV_POIDS_CIBLE_G) . ' g atteint vers'],
            [$kg > 0 ? prev_fmt($kg) . ' kg' : '—', "Aliment à prévoir ($h j)"],
            [$budget === null ? '—' : prev_fmt($budget) . ' FCFA', "Budget aliment ($h j)"],
        ];
        $res['methode'] = "Poids : droite ajustée sur les 3 dernières pesées. "
            . "Aliment : tendance linéaire de la consommation par sujet sur 7 jours, effectif ajusté de la mortalité moyenne. "
            . "Prix : dernier achat d'aliment du lot.";
    }

    $res['disponible'] = true;
    return $res;
}
