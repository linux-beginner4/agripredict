<?php
/**
 * Moteur d'alertes d'AgriPredict CI.
 * Règles statistiques simples, vérifiables et expliquées en clair :
 *   1. Mortalité anormale   : morts du jour >= 3 et >= 3 x la moyenne des 14 jours précédents
 *   2. Baisse de ponte      : taux <= 85 % du taux habituel (moyenne des 7 jours avant l'épisode)
 *   3. Baisse d'appétit     : aliment par sujet <= 90 % de la moyenne des 3 jours précédents
 *   4. Prix de l'aliment    : prix/kg d'un achat >= 110 % de la médiane des 4 achats précédents
 *   5. Lot terminé en perte : marge négative
 *   6. Vaccins              : en retard, ou prévus dans les 3 jours
 */
require_once __DIR__ . '/lots.php';

const NIVEAUX_ALERTES = ['info' => 'Info', 'attention' => 'Attention', 'critique' => 'Critique'];

/* ---------- Petits outils ---------- */

function alr_moyenne(array $v): ?float {
    $v = array_values(array_filter($v, fn($x) => $x !== null));
    return $v ? array_sum($v) / count($v) : null;
}

function alr_mediane(array $v): ?float {
    $v = array_values(array_filter($v, fn($x) => $x !== null));
    if (!$v) return null;
    sort($v);
    $n = count($v);
    return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
}

function alr_jm(string $iso): string {
    return date('d/m', strtotime($iso));
}

function alr_nb(float $x, int $d = 1): string {
    return number_format($x, $d, ',', ' ');
}

/**
 * Détecte les épisodes de baisse dans une série.
 * Un épisode démarre quand la valeur <= $seuilDebut x base (base = moyenne des $fenetre jours précédents,
 * figée pendant l'épisode) et dure tant que la valeur reste <= $seuilFin x base.
 */
function alr_baisses(array $v, int $fenetre, float $seuilDebut, float $seuilFin): array {
    $episodes = [];
    $ep = null;
    $n = count($v);

    for ($i = $fenetre; $i < $n; $i++) {
        $x = $v[$i];
        if ($x === null) continue;

        if ($ep === null) {
            $base = alr_moyenne(array_slice($v, $i - $fenetre, $fenetre));
            if ($base !== null && $base > 0 && $x <= $seuilDebut * $base) {
                $ep = ['debut' => $i, 'fin' => $i, 'base' => $base, 'min' => $x, 'en_cours' => false];
            }
        } elseif ($x <= $seuilFin * $ep['base']) {
            $ep['fin'] = $i;
            $ep['min'] = min($ep['min'], $x);
        } else {
            $episodes[] = $ep;
            $ep = null;
        }
    }
    if ($ep !== null) {
        $ep['en_cours'] = ($ep['fin'] === $n - 1);
        $episodes[] = $ep;
    }
    return $episodes;
}

/* ---------- Analyse d'un lot ---------- */

/** Retourne la liste des alertes [date_alerte, type, niveau, message] calculées pour un lot */
function alertes_analyser(array $lot): array {
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT date_suivi, mortalite, aliment_kg, oeufs_produits
         FROM suivis_quotidiens WHERE lot_id = ? ORDER BY date_suivi'
    );
    $st->execute([$lot['id']]);
    $rows = $st->fetchAll();

    $dates = $morts = $vivants = $gSujet = $taux = $cumKg = [];
    $v = (int)$lot['effectif_initial'];
    $kg = 0.0;
    foreach ($rows as $r) {
        $m = (int)$r['mortalite'];
        $v = max(0, $v - $m);
        $kg += (float)$r['aliment_kg'];
        $dates[]   = $r['date_suivi'];
        $morts[]   = $m;
        $vivants[] = $v;
        $cumKg[]   = $kg;
        $gSujet[]  = $v > 0 ? (float)$r['aliment_kg'] * 1000 / $v : null;
        $taux[]    = ($r['oeufs_produits'] !== null && $v > 0) ? (int)$r['oeufs_produits'] / $v * 100 : null;
    }
    $n = count($rows);
    $alertes = [];

    /* 1. Mortalité anormale */
    for ($i = 7; $i < $n; $i++) {
        $debut = max(0, $i - 14);
        $base  = alr_moyenne(array_slice($morts, $debut, $i - $debut));
        $m     = $morts[$i];
        if ($m >= 3 && $m >= 3 * ($base + 0.3)) {
            $part = $m / max(1, $vivants[$i] + $m) * 100;
            $alertes[] = [
                'date'    => $dates[$i],
                'type'    => 'mortalite',
                'niveau'  => ($part >= 2 || $m >= 10) ? 'critique' : 'attention',
                'message' => sprintf(
                    "Mortalité anormale le %s : %d morts (habituellement %s par jour, soit %s %% de l'effectif).",
                    alr_jm($dates[$i]), $m, alr_nb($base), alr_nb($part)
                ),
            ];
        }
    }

    /* 2. Baisse de ponte (pondeuses) */
    if ($lot['type_production'] === 'pondeuse') {
        foreach (alr_baisses($taux, 7, 0.85, 0.93) as $ep) {
            $baisse = 1 - $ep['min'] / $ep['base'];
            $jours  = $ep['fin'] - $ep['debut'] + 1;
            $periode = $ep['en_cours']
                ? 'depuis le ' . alr_jm($dates[$ep['debut']]) . ' (en cours)'
                : ($jours > 1
                    ? 'du ' . alr_jm($dates[$ep['debut']]) . ' au ' . alr_jm($dates[$ep['fin']]) . " ($jours jours)"
                    : 'le ' . alr_jm($dates[$ep['debut']]));
            $alertes[] = [
                'date'    => $dates[$ep['debut']],
                'type'    => 'ponte',
                'niveau'  => $baisse >= 0.25 ? 'critique' : 'attention',
                'message' => sprintf(
                    'Baisse de ponte %s : %s %% au plus bas contre %s %% habituellement (-%s %%).',
                    $periode, alr_nb($ep['min']), alr_nb($ep['base']), alr_nb($baisse * 100, 0)
                ),
            ];
        }
    }

    /* 3. Baisse de consommation d'aliment */
    // Poulets de chair : la consommation par sujet augmente chaque jour, on tolère donc moins de recul
    $seuils = $lot['type_production'] === 'chair' ? [0.92, 0.96] : [0.90, 0.95];
    foreach (alr_baisses($gSujet, 3, $seuils[0], $seuils[1]) as $ep) {
        $baisse = 1 - $ep['min'] / $ep['base'];
        $jours  = $ep['fin'] - $ep['debut'] + 1;
        $periode = $ep['en_cours']
            ? 'depuis le ' . alr_jm($dates[$ep['debut']]) . ' (en cours)'
            : ($jours > 1
                ? 'du ' . alr_jm($dates[$ep['debut']]) . ' au ' . alr_jm($dates[$ep['fin']])
                : 'le ' . alr_jm($dates[$ep['debut']]));
        $alertes[] = [
            'date'    => $dates[$ep['debut']],
            'type'    => 'aliment',
            'niveau'  => $baisse >= 0.30 ? 'critique' : 'attention',
            'message' => sprintf(
                "Baisse de consommation d'aliment %s : %s g par sujet contre %s g habituellement (-%s %%). À surveiller : chaleur, eau, santé du lot.",
                $periode, alr_nb($ep['min'], 0), alr_nb($ep['base'], 0), alr_nb($baisse * 100, 0)
            ),
        ];
    }

    /* 4. Hausse du prix de l'aliment (prix/kg de chaque achat) */
    $st = $pdo->prepare(
        "SELECT date_depense, montant_fcfa FROM depenses
         WHERE lot_id = ? AND categorie = 'aliment' ORDER BY date_depense, id"
    );
    $st->execute([$lot['id']]);
    $kgJusqua = function (string $d) use ($dates, $cumKg): float {
        $total = 0.0;
        foreach ($dates as $i => $dt) {
            if ($dt <= $d) $total = $cumKg[$i]; else break;
        }
        return $total;
    };
    $prix = [];
    $kgPrec = 0.0;
    foreach ($st->fetchAll() as $a) {
        $kgTot = $kgJusqua($a['date_depense']);
        $kgAchat = $kgTot - $kgPrec;
        $kgPrec = $kgTot;
        if ($kgAchat > 0) {
            $prix[] = ['date' => $a['date_depense'], 'p' => (int)$a['montant_fcfa'] / $kgAchat];
        }
    }
    $precedentSignale = false;
    for ($j = 3; $j < count($prix); $j++) {
        $histo = array_slice($prix, max(0, $j - 4), min(4, $j));
        $base  = alr_mediane(array_column($histo, 'p'));
        $ratio = $base > 0 ? $prix[$j]['p'] / $base : 1;
        $signale = $ratio >= 1.10;
        if ($signale && !$precedentSignale) {
            $alertes[] = [
                'date'    => $prix[$j]['date'],
                'type'    => 'cout',
                'niveau'  => $ratio >= 1.20 ? 'critique' : 'attention',
                'message' => sprintf(
                    "Prix de l'aliment en hausse : %s FCFA/kg contre %s FCFA/kg habituellement (+%s %%).",
                    alr_nb($prix[$j]['p'], 0), alr_nb($base, 0), alr_nb(($ratio - 1) * 100, 0)
                ),
            ];
        }
        $precedentSignale = $signale;
    }

    /* 5. Lot terminé avec une marge négative */
    if ($lot['statut'] === 'termine') {
        $dep = $pdo->prepare('SELECT COALESCE(SUM(montant_fcfa), 0) FROM depenses WHERE lot_id = ?');
        $dep->execute([$lot['id']]);
        $ven = $pdo->prepare('SELECT COALESCE(SUM(montant_fcfa), 0) FROM ventes WHERE lot_id = ?');
        $ven->execute([$lot['id']]);
        $totalDep = (int)$dep->fetchColumn();
        $totalVen = (int)$ven->fetchColumn();
        if ($totalVen - $totalDep < 0) {
            $alertes[] = [
                'date'    => $lot['date_fin'] ?: ($dates ? end($dates) : $lot['date_arrivee']),
                'type'    => 'cout',
                'niveau'  => 'critique',
                'message' => sprintf(
                    'Lot terminé avec une marge négative : %s FCFA (dépenses %s FCFA, ventes %s FCFA).',
                    alr_nb($totalVen - $totalDep, 0), alr_nb($totalDep, 0), alr_nb($totalVen, 0)
                ),
            ];
        }
    }

    /* 6. Vaccins en retard ou à faire dans les 3 jours */
    if ($lot['statut'] !== 'termine') {
        try {
            $st = $pdo->prepare(
                "SELECT nom, date_prevue FROM vaccinations
                 WHERE lot_id = ? AND statut = 'prevu' ORDER BY date_prevue"
            );
            $st->execute([$lot['id']]);
            $aujourdhui = date('Y-m-d');
            $limite = date('Y-m-d', strtotime('+3 days'));
            foreach ($st->fetchAll() as $vc) {
                if ($vc['date_prevue'] < $aujourdhui) {
                    $retard = (int)round((strtotime($aujourdhui) - strtotime($vc['date_prevue'])) / 86400);
                    $alertes[] = [
                        'date'    => $vc['date_prevue'],
                        'type'    => 'vaccin',
                        'niveau'  => $retard >= 3 ? 'critique' : 'attention',
                        'message' => sprintf(
                            'Vaccin en retard : %s (prévu le %s). Pensez à le réaliser ou à le replanifier avec votre vétérinaire.',
                            $vc['nom'], alr_jm($vc['date_prevue'])
                        ),
                    ];
                } elseif ($vc['date_prevue'] <= $limite) {
                    $alertes[] = [
                        'date'    => $vc['date_prevue'],
                        'type'    => 'vaccin',
                        'niveau'  => 'info',
                        'message' => sprintf('Vaccin à faire bientôt : %s, prévu le %s.', $vc['nom'], alr_jm($vc['date_prevue'])),
                    ];
                }
            }
        } catch (Throwable $ex) {
            // table « vaccinations » absente (migration non importée) : on ignore ces alertes
        }
    }

    return $alertes;
}

/* ---------- Stockage ---------- */

/** Recalcule les alertes d'un lot et les enregistre (conserve l'état « vue » des alertes identiques) */
function alertes_synchroniser(array $lot): void {
    $pdo = db();
    $nouvelles = alertes_analyser($lot);

    $st = $pdo->prepare('SELECT date_alerte, type, message, vue FROM alertes WHERE lot_id = ?');
    $st->execute([$lot['id']]);
    $vues = [];
    foreach ($st->fetchAll() as $a) {
        $vues[$a['date_alerte'] . '|' . $a['type'] . '|' . $a['message']] = (int)$a['vue'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM alertes WHERE lot_id = ?')->execute([$lot['id']]);
        $ins = $pdo->prepare(
            'INSERT INTO alertes (lot_id, date_alerte, type, niveau, message, vue) VALUES (?,?,?,?,?,?)'
        );
        foreach ($nouvelles as $a) {
            $cle = $a['date'] . '|' . $a['type'] . '|' . $a['message'];
            try {
                $ins->execute([$lot['id'], $a['date'], $a['type'], $a['niveau'], $a['message'], $vues[$cle] ?? 0]);
            } catch (Throwable $ex) {
                // une alerte refusée (ex. type « vaccin » absent de l'ENUM) ne doit pas bloquer les autres
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
    }
}

function alertes_synchroniser_elevage(int $elevageId): void {
    $st = db()->prepare('SELECT * FROM lots WHERE elevage_id = ?');
    $st->execute([$elevageId]);
    foreach ($st->fetchAll() as $lot) {
        alertes_synchroniser($lot);
    }
}

/** Alertes d'un élevage (ou d'un seul lot), les plus récentes et les plus graves d'abord */
function alertes_liste(int $elevageId, ?int $lotId = null, int $limite = 0): array {
    $sql = "SELECT a.id, a.lot_id, a.date_alerte, a.type, a.niveau, a.message, a.vue, l.nom AS lot_nom
            FROM alertes a JOIN lots l ON l.id = a.lot_id
            WHERE l.elevage_id = ?" . ($lotId !== null ? ' AND a.lot_id = ?' : '') . "
            ORDER BY a.date_alerte DESC,
                     CASE a.niveau WHEN 'critique' THEN 0 WHEN 'attention' THEN 1 ELSE 2 END, a.id DESC"
         . ($limite > 0 ? ' LIMIT ' . (int)$limite : '');
    $st = db()->prepare($sql);
    $st->execute($lotId !== null ? [$elevageId, $lotId] : [$elevageId]);
    return $st->fetchAll();
}

/** Nombre d'alertes non vues de l'utilisateur connecté */
function alertes_non_vues(): int {
    $st = db()->prepare(
        'SELECT COUNT(*) FROM alertes a
         JOIN lots l ON l.id = a.lot_id
         JOIN elevages e ON e.id = l.elevage_id
         WHERE e.utilisateur_id = ? AND a.vue = 0'
    );
    $st->execute([$_SESSION['user_id'] ?? 0]);
    return (int)$st->fetchColumn();
}

/** HTML d'une liste d'alertes */
function alertes_html(array $alertes, bool $avecLot = true): string {
    if (!$alertes) {
        return '<p class="muted">Aucune alerte : tout est dans la normale.</p>';
    }
    $h = '';
    foreach ($alertes as $a) {
        $niv = $a['niveau'];
        $h .= '<div class="alerte alerte-' . e($niv) . ($a['vue'] ? '' : ' alerte-nouvelle') . '">'
            . '<div class="alerte-tete"><span class="badge-niveau badge-' . e($niv) . '">'
            . e(NIVEAUX_ALERTES[$niv] ?? $niv) . '</span> '
            . ($avecLot ? '<a href="lot.php?id=' . (int)$a['lot_id'] . '">' . e($a['lot_nom']) . '</a> · ' : '')
            . e(date('d/m/Y', strtotime($a['date_alerte']))) . '</div>'
            . '<p>' . e($a['message']) . '</p></div>';
    }
    return $h;
}
