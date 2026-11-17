<?php
require_once __DIR__ . '/analyse.php';

/** Produits vendables : libellé, unité de quantité */
const PRODUITS_VENTE = [
    'oeufs'   => ['label' => 'Œufs',                'unite' => 'plateaux de 30 œufs'],
    'poulet'  => ['label' => 'Poulets / poules',    'unite' => 'sujets'],
    'fientes' => ['label' => 'Fientes (fumier)',    'unite' => 'sacs'],
    'autre'   => ['label' => 'Autre',               'unite' => 'unités'],
];

/** Produits proposés selon le type de lot */
function produits_pour_lot(array $lot): array {
    $cles = $lot['type_production'] === 'pondeuse'
        ? ['oeufs', 'poulet', 'fientes', 'autre']
        : ['poulet', 'fientes', 'autre'];
    return array_intersect_key(PRODUITS_VENTE, array_flip($cles));
}

/** "12 500" ou "12500" -> 12500 ; null si invalide */
function montant_entier(string $s): ?int {
    $s = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $s);
    return ($s !== '' && ctype_digit($s) && (int)$s > 0) ? (int)$s : null;
}

function date_valide(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

/** Dernières dépenses et ventes d'un lot, mélangées, de la plus récente à la plus ancienne */
function finances_recentes(int $lotId, int $limite = 12): array {
    $pdo = db();

    $st = $pdo->prepare(
        'SELECT id, date_depense AS date_op, categorie, libelle, montant_fcfa
         FROM depenses WHERE lot_id = ? ORDER BY date_depense DESC, id DESC LIMIT ' . (int)$limite
    );
    $st->execute([$lotId]);
    $ops = [];
    foreach ($st->fetchAll() as $d) {
        $d['type'] = 'depense';
        $ops[] = $d;
    }

    $st = $pdo->prepare(
        'SELECT id, date_vente AS date_op, produit, quantite, prix_unitaire_fcfa, montant_fcfa
         FROM ventes WHERE lot_id = ? ORDER BY date_vente DESC, id DESC LIMIT ' . (int)$limite
    );
    $st->execute([$lotId]);
    foreach ($st->fetchAll() as $v) {
        $v['type'] = 'vente';
        $ops[] = $v;
    }

    usort($ops, fn($a, $b) => [$b['date_op'], $b['id']] <=> [$a['date_op'], $a['id']]);
    return array_slice($ops, 0, $limite);
}
