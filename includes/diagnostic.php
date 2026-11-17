<?php
/**
 * Moteur d'aide à l'orientation sanitaire.
 * Principe : on compare les symptômes cochés à ceux de chaque affection (base de connaissances locale,
 * sans connexion internet) et on classe les affections par correspondance.
 * Ce n'est PAS un diagnostic : l'outil oriente, rappelle les gestes de prudence et renvoie vers un vétérinaire.
 */
require_once __DIR__ . '/diagnostic_data.php';
require_once __DIR__ . '/alertes.php';
require_once __DIR__ . '/vaccins.php';

const DIAG_GRAVITES = ['moderee' => 'À surveiller', 'grave' => 'Grave', 'critique' => 'Très grave'];

/** Tous les symptômes à plat : identifiant => libellé */
function diag_symptomes_plats(): array {
    $out = [];
    foreach (SYMPTOMES as $liste) {
        foreach ($liste as $id => $label) $out[$id] = $label;
    }
    return $out;
}

/** Contexte du lot : mortalité anormale récente et vaccins enregistrés */
function diag_contexte_lot(array $lot): array {
    $ctx = ['mortalite_recente' => false, 'vaccins' => []];
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM alertes WHERE lot_id = ? AND type = 'mortalite' AND date_alerte >= ?");
        $st->execute([$lot['id'], date('Y-m-d', strtotime('-7 days'))]);
        $ctx['mortalite_recente'] = (int)$st->fetchColumn() > 0;
    } catch (Throwable $ex) {
    }
    try {
        $ctx['vaccins'] = vaccins_liste((int)$lot['id']);
    } catch (Throwable $ex) {
    }
    return $ctx;
}

/** Phrase sur le vaccin associé à une affection, d'après les vaccins enregistrés pour le lot */
function diag_note_vaccin(?string $motCle, array $vaccins): ?string {
    if ($motCle === null) return null;
    $trouves = array_filter($vaccins, fn($v) => stripos($v['nom'], $motCle) !== false);
    if (!$trouves) {
        return "Aucun vaccin « $motCle » n'est enregistré pour ce lot (à vérifier avec votre carnet de vaccination).";
    }
    foreach ($trouves as $v) {
        if (vaccin_statut($v) === 'retard') {
            return "Le vaccin « {$v['nom']} » de ce lot est en retard (prévu le " . date('d/m/Y', strtotime($v['date_prevue'])) . ').';
        }
    }
    $faits = array_filter($trouves, fn($v) => $v['statut'] === 'fait');
    if ($faits) {
        $v = end($faits);
        return "Vaccin « {$v['nom']} » enregistré le " . date('d/m/Y', strtotime($v['date_realisee'])) . " : la vaccination réduit le risque mais ne le supprime pas.";
    }
    return "Le vaccin « $motCle » est prévu mais pas encore réalisé pour ce lot.";
}

/**
 * Analyse des symptômes.
 * Retourne ['urgence' => 'elevee'|'surveiller', 'raisons' => [...], 'maladies' => [...3 meilleures...]]
 */
function diag_analyser(array $ids, string $touches, array $contexte): array {
    $plats = diag_symptomes_plats();
    $ids = array_values(array_unique(array_filter($ids, fn($i) => isset($plats[$i]))));
    $resultats = [];

    foreach (MALADIES as $cle => $m) {
        $total = array_sum($m['symptomes']);
        $match = 0;
        $communs = [];
        foreach ($ids as $id) {
            if (isset($m['symptomes'][$id])) {
                $match += $m['symptomes'][$id];
                $communs[] = $id;
            }
        }
        if (!$communs || !$ids) continue;

        $couverture = $match / $total;                      // part des signes typiques retrouvés
        $precision  = count($communs) / count($ids);        // part des signes observés expliqués
        $facteur    = min(1, count($communs) / 2);          // un seul signe ne suffit pas à conclure
        $score      = (0.5 * $couverture + 0.5 * $precision) * $facteur;

        $resultats[] = [
            'cle' => $cle, 'nom' => $m['nom'], 'gravite' => $m['gravite'], 'score' => $score,
            'niveau' => $score >= 0.6 ? 'forte' : ($score >= 0.4 ? 'moyenne' : 'faible'),
            'communs' => array_map(fn($i) => $plats[$i], $communs),
            'nb_communs' => count($communs), 'nb_typiques' => count($m['symptomes']),
            'isolement' => $m['isolement'], 'declaration' => $m['declaration'],
            'conseil' => $m['conseil'],
            'vaccin' => diag_note_vaccin($m['vaccin'], $contexte['vaccins'] ?? []),
        ];
    }

    $ordreGravite = ['critique' => 0, 'grave' => 1, 'moderee' => 2];
    usort($resultats, fn($a, $b) => [$b['score'], $ordreGravite[$a['gravite']]] <=> [$a['score'], $ordreGravite[$b['gravite']]]);
    $retenues = array_slice(array_values(array_filter($resultats, fn($r) => $r['score'] >= 0.2)), 0, 3);

    // Urgence : on préfère la prudence
    $raisons = [];
    foreach ($retenues as $r) {
        if ($r['gravite'] === 'critique' && $r['score'] >= 0.4) {
            $raisons[] = 'Une affection très grave et contagieuse est évoquée (' . $r['nom'] . ').';
            break;
        }
    }
    if (in_array('mortalite', $ids, true)) {
        $raisons[] = 'Une mortalité anormale ou des morts subites sont signalées.';
    }
    if (in_array($touches, ['beaucoup', 'presque_tous'], true)) {
        $raisons[] = 'Une part importante du lot est touchée.';
    }
    if (!empty($contexte['mortalite_recente'])) {
        $raisons[] = "L'application a détecté une mortalité anormale sur ce lot ces 7 derniers jours.";
    }

    return [
        'urgence'  => $raisons ? 'elevee' : 'surveiller',
        'raisons'  => $raisons,
        'maladies' => $retenues,
    ];
}

/** Dernières consultations d'un lot */
function diag_dernieres(int $lotId, int $n = 3): array {
    $st = db()->prepare(
        'SELECT id, date_consultation, resultat, urgence FROM diagnostics
         WHERE lot_id = ? ORDER BY id DESC LIMIT ' . (int)$n
    );
    $st->execute([$lotId]);
    return $st->fetchAll();
}

/** Consultation si elle appartient à un lot de l'utilisateur connecté */
function diag_autorise(int $id): ?array {
    $st = db()->prepare(
        'SELECT d.* FROM diagnostics d
         JOIN lots l ON l.id = d.lot_id
         JOIN elevages e ON e.id = l.elevage_id
         WHERE d.id = ? AND e.utilisateur_id = ?'
    );
    $st->execute([$id, $_SESSION['user_id'] ?? 0]);
    return $st->fetch() ?: null;
}

/** Contacts vétérinaires de l'élevage */
function veterinaires_liste(int $elevageId): array {
    $st = db()->prepare('SELECT id, nom, telephone FROM contacts_veterinaires WHERE elevage_id = ? ORDER BY id');
    $st->execute([$elevageId]);
    return $st->fetchAll();
}
