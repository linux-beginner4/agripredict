<?php
require_once __DIR__ . '/functions.php';

// Fuseau horaire de la Côte d'Ivoire (dates de saisie)
date_default_timezone_set('Africa/Abidjan');

/** Élevage de l'utilisateur connecté (un seul pour le MVP) */
function elevage_courant(): ?array {
    $st = db()->prepare('SELECT id, nom, localite FROM elevages WHERE utilisateur_id = ? ORDER BY id LIMIT 1');
    $st->execute([$_SESSION['user_id'] ?? 0]);
    return $st->fetch() ?: null;
}

/** Retourne le lot s'il appartient bien à l'utilisateur connecté, sinon null */
function lot_autorise(int $id): ?array {
    $st = db()->prepare(
        'SELECT l.* FROM lots l
         JOIN elevages e ON e.id = l.elevage_id
         WHERE l.id = ? AND e.utilisateur_id = ?'
    );
    $st->execute([$id, $_SESSION['user_id'] ?? 0]);
    return $st->fetch() ?: null;
}

/** Indicateurs de base d'un lot */
function lot_resume(array $lot): array {
    $st = db()->prepare(
        'SELECT COALESCE(SUM(mortalite),0)      AS morts,
                COALESCE(SUM(aliment_kg),0)     AS aliment,
                COALESCE(SUM(oeufs_produits),0) AS oeufs,
                COUNT(*)                        AS jours
         FROM suivis_quotidiens WHERE lot_id = ?'
    );
    $st->execute([$lot['id']]);
    $r = $st->fetch();

    $initial = max(1, (int)$lot['effectif_initial']);
    return [
        'morts'           => (int)$r['morts'],
        'aliment_kg'      => (float)$r['aliment'],
        'oeufs'           => (int)$r['oeufs'],
        'jours_saisis'    => (int)$r['jours'],
        'effectif_actuel' => (int)$lot['effectif_initial'] - (int)$r['morts'],
        'taux_mortalite'  => round($r['morts'] / $initial * 100, 1),
    ];
}

/** Convertit "12,5" en "12.5" */
function num(string $s): string {
    return str_replace(',', '.', trim($s));
}

function libelle_type(string $t): string {
    return $t === 'chair' ? 'Poulets de chair' : 'Pondeuses';
}
