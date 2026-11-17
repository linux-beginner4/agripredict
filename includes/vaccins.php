<?php
require_once __DIR__ . '/lots.php';

const VOIES_VACCIN = [
    'eau'       => 'Eau de boisson',
    'oculaire'  => 'Goutte oculaire / nasale',
    'injection' => 'Injection',
    'spray'     => 'Spray',
    'autre'     => 'Autre',
];

/**
 * Calendriers types, INDICATIFS : [nom, âge en jours après l'arrivée du lot].
 * À faire valider par un vétérinaire ou par le calendrier du fournisseur de poussins ;
 * chaque ligne reste modifiable ou supprimable par l'éleveur.
 */
const MODELES_VACCINATION = [
    'chair' => [
        ['Newcastle (1re dose)', 7],
        ['Gumboro (1re dose)', 14],
        ['Newcastle (rappel)', 21],
    ],
    'pondeuse' => [
        ['Newcastle (rappel)', 30],
        ['Newcastle (rappel)', 90],
    ],
];

/** Vaccinations d'un lot, de la plus proche à la plus lointaine */
function vaccins_liste(int $lotId): array {
    $st = db()->prepare('SELECT * FROM vaccinations WHERE lot_id = ? ORDER BY date_prevue, id');
    $st->execute([$lotId]);
    return $st->fetchAll();
}

/** Vaccination si elle appartient à un lot de l'utilisateur connecté */
function vaccin_autorise(int $id): ?array {
    $st = db()->prepare(
        'SELECT v.* FROM vaccinations v
         JOIN lots l ON l.id = v.lot_id
         JOIN elevages e ON e.id = l.elevage_id
         WHERE v.id = ? AND e.utilisateur_id = ?'
    );
    $st->execute([$id, $_SESSION['user_id'] ?? 0]);
    return $st->fetch() ?: null;
}

/** fait | retard | bientot (dans les 3 jours) | prevu */
function vaccin_statut(array $v, ?string $aujourdhui = null): string {
    if ($v['statut'] === 'fait') return 'fait';
    $jour = $aujourdhui ?? date('Y-m-d');
    if ($v['date_prevue'] < $jour) return 'retard';
    if ($v['date_prevue'] <= date('Y-m-d', strtotime("$jour +3 days"))) return 'bientot';
    return 'prevu';
}

/** Ajoute le calendrier type du lot (sans doublon) ; retourne le nombre de lignes ajoutées */
function vaccins_ajouter_modele(array $lot): int {
    $modele = MODELES_VACCINATION[$lot['type_production']] ?? [];
    $existants = [];
    foreach (vaccins_liste((int)$lot['id']) as $v) {
        $existants[$v['nom'] . '|' . $v['age_jour']] = true;
    }

    $ins = db()->prepare(
        'INSERT INTO vaccinations (lot_id, nom, age_jour, date_prevue, statut) VALUES (?,?,?,?,?)'
    );
    $arrivee = new DateTimeImmutable($lot['date_arrivee']);
    $n = 0;
    foreach ($modele as [$nom, $age]) {
        if (isset($existants[$nom . '|' . $age])) continue;
        $ins->execute([$lot['id'], $nom, $age, $arrivee->modify("+$age days")->format('Y-m-d'), 'prevu']);
        $n++;
    }
    return $n;
}

function vaccin_date_valide(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}
