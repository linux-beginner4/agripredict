<?php
/**
 * Données de démonstration (FICTIVES) pour AgriPredict CI.
 * Crée 2 lots rattachés à l'élevage de l'utilisateur connecté :
 *   - Pondeuses bâtiment A : 200 sujets, 56 jours de suivi
 *   - Poulets de chair lot 1 : 500 sujets, cycle complet de 42 jours, vendu
 * Les lots sont marqués est_demo = 1. Relancer le script remplace les lots démo existants.
 * Des anomalies sont volontairement insérées pour déclencher les alertes.
 */
require_once __DIR__ . '/includes/lots.php';
exiger_connexion();

$elevage = elevage_courant();
if (!$elevage) {
    flash('erreur', 'Aucun élevage associé à ce compte.');
    redirect('dashboard.php');
}

function alea(float $min, float $max): float {
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

function demo_generer(PDO $pdo, int $elevageId): array {
    mt_srand(2026); // résultats reproductibles

    // Remplace les anciens lots démo (cascade sur suivis, dépenses, ventes, alertes)
    $pdo->prepare('DELETE FROM lots WHERE elevage_id = ? AND est_demo = 1')->execute([$elevageId]);

    $insLot = $pdo->prepare(
        'INSERT INTO lots (elevage_id, nom, type_production, effectif_initial, date_arrivee, date_fin, statut, est_demo)
         VALUES (?,?,?,?,?,?,?,1)'
    );
    $insSuivi = $pdo->prepare(
        'INSERT INTO suivis_quotidiens
            (lot_id, date_suivi, mortalite, aliment_kg, eau_litres, oeufs_produits, poids_moyen_g, remarque)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $insDepense = $pdo->prepare(
        'INSERT INTO depenses (lot_id, date_depense, categorie, libelle, montant_fcfa) VALUES (?,?,?,?,?)'
    );
    $insVente = $pdo->prepare(
        'INSERT INTO ventes (lot_id, date_vente, produit, quantite, prix_unitaire_fcfa, montant_fcfa)
         VALUES (?,?,?,?,?,?)'
    );

    $insVacc = $pdo->prepare(
        'INSERT INTO vaccinations (lot_id, nom, age_jour, date_prevue, statut, date_realisee, produit, voie)
         VALUES (?,?,?,?,?,?,?,?)'
    );

    $today = new DateTimeImmutable('today');
    $nbSuivis = 0;

    // Vaccination : $faitJ = jour de réalisation (après l'arrivée), null = pas encore fait
    $vacciner = function (int $lot, DateTimeImmutable $debut, string $nom, int $age, ?int $faitJ,
                          ?string $produit = null, ?string $voie = null) use ($insVacc): void {
        $insVacc->execute([
            $lot, $nom, $age, $debut->modify("+$age days")->format('Y-m-d'),
            $faitJ === null ? 'prevu' : 'fait',
            $faitJ === null ? null : $debut->modify("+$faitJ days")->format('Y-m-d'),
            $produit, $voie,
        ]);
    };

    /* ============ Lot A : pondeuses (56 jours, se termine hier) ============ */
    $debutA = $today->modify('-56 days');
    $insLot->execute([$elevageId, 'Pondeuses bâtiment A (démo)', 'pondeuse', 200,
                      $debutA->format('Y-m-d'), null, 'en_cours']);
    $lotA = (int)$pdo->lastInsertId();

    // Vaccins pondeuses : un fait, un en retard (alerte), un à faire bientôt, un lointain
    $vacciner($lotA, $debutA, 'Newcastle (rappel)', 30, 31, 'Newcastle La Sota', 'eau');
    $vacciner($lotA, $debutA, 'Bronchite infectieuse (rappel)', 54, null);
    $vacciner($lotA, $debutA, 'Newcastle (rappel)', 58, null);
    $vacciner($lotA, $debutA, 'Newcastle (rappel)', 90, null);

    $vivants = 200;
    $alimSem = 0.0;
    $oeufsSem = 0;

    $insDepense->execute([$lotA, $debutA->format('Y-m-d'), 'poussins',
                          'Achat de 200 poulettes prêtes à pondre', 200 * 3800]);

    for ($i = 0; $i < 56; $i++) {
        $date = $debutA->modify("+$i days");
        $remarque = null;

        $morts = (mt_rand(0, 99) < 12) ? 1 : 0;
        if ($i === 44) { $morts = 7; $remarque = 'Mortalité anormale, vétérinaire appelé'; }
        $vivants -= $morts;

        $taux = min(0.92, 0.35 + $i * 0.025) + alea(-0.015, 0.015);   // montée en ponte
        $aliment = $vivants * 0.115 * alea(0.97, 1.03);                // kg/jour
        if ($i >= 38 && $i <= 41) {                                    // vague de chaleur
            $taux *= 0.78;
            $aliment *= 0.88;
            $remarque = 'Forte chaleur';
        }
        if ($i >= 45 && $i <= 47) { $taux *= 0.90; }                   // convalescence

        $oeufs = (int)round($vivants * $taux);
        $eau   = $vivants * 0.25 * alea(0.95, 1.05);

        $insSuivi->execute([$lotA, $date->format('Y-m-d'), $morts, round($aliment, 2),
                            round($eau, 1), $oeufs, null, $remarque]);
        $nbSuivis++;

        $alimSem += $aliment;
        $oeufsSem += $oeufs;

        if ($i % 7 === 6) {                                            // fin de semaine
            $sem = intdiv($i, 7);                                      // 0..7
            $prixKg = ($sem >= 6) ? 615 : 520;                         // hausse du prix de l'aliment
            $insDepense->execute([$lotA, $date->format('Y-m-d'), 'aliment',
                                  'Aliment pondeuse, semaine ' . ($sem + 1), (int)round($alimSem * $prixKg)]);

            $plateaux = (int)floor($oeufsSem / 30 * 0.97);             // 3 % de casse / usage perso
            $insVente->execute([$lotA, $date->format('Y-m-d'), 'oeufs', $plateaux, 2400, $plateaux * 2400]);

            $alimSem = 0.0;
            $oeufsSem = 0;
        }
    }
    $insDepense->execute([$lotA, $debutA->modify('+10 days')->format('Y-m-d'), 'sante', 'Vitamines et vaccin', 25000]);
    $insDepense->execute([$lotA, $debutA->modify('+45 days')->format('Y-m-d'), 'sante', 'Visite vétérinaire', 15000]);
    $insDepense->execute([$lotA, $debutA->modify('+27 days')->format('Y-m-d'), 'main_oeuvre', 'Salaire ouvrier (mois 1)', 40000]);
    $insDepense->execute([$lotA, $debutA->modify('+55 days')->format('Y-m-d'), 'main_oeuvre', 'Salaire ouvrier (mois 2)', 40000]);
    $insDepense->execute([$lotA, $debutA->modify('+30 days')->format('Y-m-d'), 'energie', 'Électricité du bâtiment', 15000]);

    /* ============ Lot B : poulets de chair (cycle de 42 jours, vendu) ============ */
    $debutB = $today->modify('-44 days');
    $finB   = $debutB->modify('+41 days');
    $insLot->execute([$elevageId, 'Poulets de chair lot 1 (démo)', 'chair', 500,
                      $debutB->format('Y-m-d'), $finB->format('Y-m-d'), 'termine']);
    $lotB = (int)$pdo->lastInsertId();

    // Vaccins poulets de chair : cycle complet, tous réalisés
    $vacciner($lotB, $debutB, 'Newcastle (1re dose)', 7, 7, 'Newcastle HB1', 'oculaire');
    $vacciner($lotB, $debutB, 'Gumboro (1re dose)', 14, 14, 'Gumboro intermédiaire', 'eau');
    $vacciner($lotB, $debutB, 'Newcastle (rappel)', 21, 22, 'Newcastle La Sota', 'eau');

    $poidsRef = [0 => 42, 7 => 175, 14 => 440, 21 => 850, 28 => 1400, 35 => 1950, 41 => 2350];
    $vivants = 500;
    $alimSem = 0.0;

    $insDepense->execute([$lotB, $debutB->format('Y-m-d'), 'poussins', 'Achat de 500 poussins chair', 500 * 650]);
    $insDepense->execute([$lotB, $debutB->format('Y-m-d'), 'energie', 'Chauffage (charbon, gaz)', 25000]);
    $insDepense->execute([$lotB, $debutB->modify('+3 days')->format('Y-m-d'), 'sante', 'Vitamines et anti-stress', 8000]);
    $insDepense->execute([$lotB, $debutB->modify('+7 days')->format('Y-m-d'), 'sante', 'Vaccin Gumboro', 20000]);
    $insDepense->execute([$lotB, $debutB->modify('+14 days')->format('Y-m-d'), 'sante', 'Vaccin Newcastle', 15000]);
    $insDepense->execute([$lotB, $debutB->modify('+30 days')->format('Y-m-d'), 'main_oeuvre', 'Salaire ouvrier', 30000]);

    for ($d = 0; $d < 42; $d++) {
        $date = $debutB->modify("+$d days");
        $remarque = null;

        $morts = ($d < 7) ? mt_rand(0, 3) : ((mt_rand(0, 99) < 25) ? 1 : 0);
        if ($d === 19) { $morts = 14; $remarque = 'Coup de chaleur, mortalité élevée'; }
        $vivants -= $morts;

        $aliment = $vivants * (0.011 + 0.0041 * $d) * alea(0.97, 1.03);
        if ($d === 20 || $d === 21) { $aliment *= 0.80; $remarque = $remarque ?? 'Baisse d\'appétit'; }
        $eau = $vivants * (0.03 + 0.0075 * $d) * alea(0.95, 1.05);

        $poids = isset($poidsRef[$d]) ? (int)round($poidsRef[$d] * alea(0.98, 1.02)) : null;

        $insSuivi->execute([$lotB, $date->format('Y-m-d'), $morts, round($aliment, 2),
                            round($eau, 1), null, $poids, $remarque]);
        $nbSuivis++;

        $alimSem += $aliment;
        if ($d % 7 === 6) {
            $sem = intdiv($d, 7);
            $insDepense->execute([$lotB, $date->format('Y-m-d'), 'aliment',
                                  'Aliment chair, semaine ' . ($sem + 1), (int)round($alimSem * 500)]);
            $alimSem = 0.0;
        }
    }
    $vendus = (int)floor($vivants * 0.97);
    $insVente->execute([$lotB, $finB->format('Y-m-d'), 'poulet', $vendus, 3200, $vendus * 3200]);

    return ['lots' => 2, 'suivis' => $nbSuivis];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $res = demo_generer($pdo, (int)$elevage['id']);
        $pdo->commit();
        flash('succes', "Données de démonstration chargées : {$res['lots']} lots, {$res['suivis']} jours de suivi.");
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('erreur', 'Échec du chargement : ' . $ex->getMessage());
    }
    redirect('dashboard.php');
}

$titre = 'Données de démonstration';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Données de démonstration</h1>
    <p>Cette action crée deux lots <strong>fictifs</strong> dans votre élevage :</p>
    <ul>
        <li>Pondeuses bâtiment A : 200 sujets, 56 jours de suivi</li>
        <li>Poulets de chair lot 1 : 500 sujets, cycle complet de 42 jours, vendu</li>
    </ul>
    <p class="muted">
        Elles incluent des anomalies volontaires (chaleur, mortalité, hausse du prix de l'aliment)
        pour illustrer les alertes. Les lots démo existants seront remplacés ; vos vrais lots ne sont pas touchés.
    </p>
    <form method="post">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Charger les données de démonstration</button>
        <a class="btn btn-secondaire" href="dashboard.php">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
