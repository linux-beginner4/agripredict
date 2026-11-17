<?php
require_once __DIR__ . '/includes/previsions.php';
require_once __DIR__ . '/includes/finances.php';
require_once __DIR__ . '/includes/vaccins.php';
require_once __DIR__ . '/includes/diagnostic.php';
require_once __DIR__ . '/includes/alertes.php';
exiger_connexion();

$lot = lot_autorise((int)($_GET['id'] ?? 0));
if (!$lot) {
    flash('erreur', 'Lot introuvable.');
    redirect('dashboard.php');
}

$estPondeuse = $lot['type_production'] === 'pondeuse';
alertes_synchroniser($lot);
$alertesLot = alertes_liste((int)$lot['elevage_id'], (int)$lot['id']);
$r      = lot_resume($lot);
$serie  = lot_serie($lot);
$fin    = lot_finances($lot);
$kpis   = lot_indicateurs($lot, $serie, $fin);
$prev   = lot_previsions($lot, $serie);
$operations = finances_recentes((int)$lot['id']);
try {
    $vaccins = vaccins_liste((int)$lot['id']);
} catch (Throwable $ex) {
    $vaccins = null; // table absente : migration non importée
}
try {
    $consultations = diag_dernieres((int)$lot['id'], 3);
} catch (Throwable $ex) {
    $consultations = null; // table absente : migration non importée
}
$aDesDonnees = count($serie['dates']) > 0;

// Dépenses non nulles pour le graphique
$depLabels = [];
$depValeurs = [];
foreach (CATEGORIES_DEPENSES as $cle => $label) {
    if ($fin['depenses'][$cle] > 0) {
        $depLabels[]  = $label;
        $depValeurs[] = $fin['depenses'][$cle];
    }
}

$donneesJs = [
    'type'     => $lot['type_production'],
    'serie'    => $serie,
    'prevision' => $prev,
    'depenses' => ['labels' => $depLabels, 'valeurs' => $depValeurs],
];

$st = db()->prepare(
    'SELECT date_suivi, mortalite, aliment_kg, oeufs_produits, poids_moyen_g
     FROM suivis_quotidiens WHERE lot_id = ? ORDER BY date_suivi DESC LIMIT 14'
);
$st->execute([$lot['id']]);
$suivis = $st->fetchAll();

$titre = $lot['nom'];
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1><?= e($lot['nom']) ?></h1>
    <p class="muted">
        <?= e(libelle_type($lot['type_production'])) ?>
        · arrivée le <?= e(date('d/m/Y', strtotime($lot['date_arrivee']))) ?>
        <?= $lot['statut'] === 'termine' ? ' · lot terminé' : '' ?>
        <?= $lot['est_demo'] ? ' · <strong>données de démonstration</strong>' : '' ?>
    </p>

    <div class="stats">
        <div class="stat"><strong><?= $r['effectif_actuel'] ?></strong><span>Effectif actuel</span></div>
        <div class="stat"><strong><?= $r['taux_mortalite'] ?> %</strong><span>Mortalité cumulée</span></div>
        <div class="stat"><strong><?= number_format($r['aliment_kg'], 0, ',', ' ') ?> kg</strong><span>Aliment consommé</span></div>
        <?php if ($estPondeuse): ?>
            <div class="stat"><strong><?= number_format($r['oeufs'], 0, ',', ' ') ?></strong><span>Œufs produits</span></div>
        <?php else: ?>
            <div class="stat"><strong><?= $r['jours_saisis'] ?></strong><span>Jours saisis</span></div>
        <?php endif; ?>
    </div>

    <a class="btn" href="suivi_nouveau.php?lot=<?= (int)$lot['id'] ?>">+ Saisie du jour</a>
</div>

<div class="card" id="vaccins">
    <h2>Vaccinations</h2>
    <?php if ($vaccins === null): ?>
        <p class="muted">Le suivi des vaccins n'est pas encore activé : importez <code>sql/migration_vaccins.sql</code> dans phpMyAdmin.</p>
    <?php else: ?>
        <?php if (!$vaccins): ?>
            <p class="muted">Aucun vaccin planifié. Chargez le calendrier type ou ajoutez vos propres vaccins.</p>
        <?php endif; ?>

        <?php
        $libellesStatut = ['fait' => 'Fait', 'retard' => 'En retard', 'bientot' => 'Bientôt', 'prevu' => 'Prévu'];
        $badges = ['fait' => 'badge-fait', 'retard' => 'badge-critique', 'bientot' => 'badge-attention', 'prevu' => ''];
        foreach ($vaccins as $vc):
            $stv = vaccin_statut($vc);
        ?>
            <div class="vaccin vaccin-<?= e($stv) ?>">
                <div>
                    <strong><?= e($vc['nom']) ?></strong>
                    <span class="badge-niveau <?= e($badges[$stv]) ?>"><?= e($libellesStatut[$stv]) ?></span><br>
                    <span class="muted">
                        <?php if ($stv === 'fait'): ?>
                            Fait le <?= e(date('d/m/Y', strtotime($vc['date_realisee']))) ?>
                            <?= $vc['produit'] ? '· ' . e($vc['produit']) : '' ?>
                            <?= $vc['voie'] ? '· ' . e(VOIES_VACCIN[$vc['voie']] ?? $vc['voie']) : '' ?>
                            <?= $vc['numero_flacon'] ? '· flacon n° ' . e($vc['numero_flacon']) : '' ?>
                        <?php else: ?>
                            Prévu le <?= e(date('d/m/Y', strtotime($vc['date_prevue']))) ?>
                            <?= $vc['age_jour'] !== null ? '· J+' . (int)$vc['age_jour'] : '' ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="vaccin-actions">
                    <?php if ($stv === 'fait'): ?>
                        <form method="post" action="vaccin_action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="annuler">
                            <input type="hidden" name="id" value="<?= (int)$vc['id'] ?>">
                            <button class="btn-mini secondaire" type="submit" title="Repasser en « prévu »">Annuler</button>
                        </form>
                    <?php else: ?>
                        <a class="btn-mini" href="vaccin_fait.php?id=<?= (int)$vc['id'] ?>">Fait</a>
                    <?php endif; ?>
                    <form method="post" action="vaccin_action.php" onsubmit="return confirm('Supprimer ce vaccin du calendrier ?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="id" value="<?= (int)$vc['id'] ?>">
                        <button class="btn-suppr" type="submit" title="Supprimer" aria-label="Supprimer ce vaccin">✕</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="btn-row">
            <a class="btn" href="vaccin_nouveau.php?lot=<?= (int)$lot['id'] ?>">+ Vaccin</a>
            <form method="post" action="vaccin_action.php" style="flex:1;display:flex;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="modele">
                <input type="hidden" name="lot" value="<?= (int)$lot['id'] ?>">
                <button class="btn btn-secondaire" type="submit" style="flex:1;">Calendrier type</button>
            </form>
        </div>
        <p class="note-methode">Le calendrier type est indicatif : à faire valider par votre vétérinaire ou selon les consignes de votre fournisseur.</p>
    <?php endif; ?>
</div>

<div class="card" id="sante">
    <h2>Santé : orientation</h2>
    <?php if ($consultations === null): ?>
        <p class="muted">Fonction non activée : importez <code>sql/migration_diagnostic.sql</code> dans phpMyAdmin.</p>
    <?php else: ?>
        <p class="muted">Un signe inhabituel ? Cochez les symptômes observés pour obtenir une orientation et les gestes de prudence.</p>
        <?php foreach ($consultations as $cs): ?>
            <a class="lot-link" href="diagnostic.php?r=<?= (int)$cs['id'] ?>">
                <strong><?= e(date('d/m/Y H:i', strtotime($cs['date_consultation']))) ?></strong>
                <?= $cs['urgence'] === 'elevee' ? '<span class="badge-niveau badge-critique">Urgent</span>' : '' ?><br>
                <span class="muted"><?= e($cs['resultat'] ?? '') ?></span>
            </a>
        <?php endforeach; ?>
        <a class="btn" href="diagnostic.php?lot=<?= (int)$lot['id'] ?>">Signaler des symptômes</a>
    <?php endif; ?>
</div>

<?php if ($aDesDonnees): ?>
<div class="card">
    <h2>Alertes</h2>
    <?= alertes_html($alertesLot, false) ?>
</div>

<div class="card">
    <h2>Indicateurs clés</h2>
    <div class="stats">
        <?php foreach ($kpis as [$valeur, $libelle]): ?>
            <div class="stat"><strong><?= e($valeur) ?></strong><span><?= e($libelle) ?></span></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h2><?= $estPondeuse ? 'Taux de ponte (%)' : 'Poids moyen (g)' ?><?= $prev['disponible'] ? ' : historique et prévision' : '' ?></h2>
    <div class="chart-box"><canvas id="c-principal"></canvas></div>
</div>

<div class="card">
    <h2>Prévisions sur <?= (int)$prev['horizon'] ?> jours <span class="badge-demo">estimation</span></h2>
    <?php if ($prev['disponible']): ?>
        <div class="stats">
            <?php foreach ($prev['kpis'] as [$valeur, $libelle]): ?>
                <div class="stat"><strong><?= e($valeur) ?></strong><span><?= e($libelle) ?></span></div>
            <?php endforeach; ?>
        </div>
        <p class="note-methode"><strong>Méthode :</strong> <?= e($prev['methode']) ?>
            Ces chiffres sont des projections indicatives à partir de l'historique du lot, pas des garanties.</p>
    <?php else: ?>
        <p class="muted"><?= e($prev['raison']) ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Mortalité par jour</h2>
    <div class="chart-box"><canvas id="c-morts"></canvas></div>
</div>

<div class="card">
    <h2>Aliment par sujet (g / jour)</h2>
    <div class="chart-box"><canvas id="c-aliment"></canvas></div>
</div>
<?php endif; ?>

<div class="card" id="finances">
    <h2>Finances</h2>
    <div class="stats stats-3">
        <div class="stat"><strong><?= e(fcfa($fin['total_depenses'])) ?></strong><span>Dépenses</span></div>
        <div class="stat"><strong><?= e(fcfa($fin['ventes'])) ?></strong><span>Ventes</span></div>
        <div class="stat">
            <strong class="<?= $fin['marge'] >= 0 ? 'pos' : 'neg' ?>"><?= e(fcfa($fin['marge'])) ?></strong>
            <span>Marge<?= $lot['statut'] === 'termine' ? '' : ' à ce jour' ?></span>
        </div>
    </div>
    <div class="btn-row">
        <a class="btn" href="depense_nouvelle.php?lot=<?= (int)$lot['id'] ?>">+ Dépense</a>
        <a class="btn" href="vente_nouvelle.php?lot=<?= (int)$lot['id'] ?>">+ Vente</a>
    </div>

    <?php if ($depValeurs): ?>
        <h3 class="sous-titre">Dépenses par catégorie (FCFA)</h3>
        <div class="chart-box chart-box-court"><canvas id="c-depenses"></canvas></div>
    <?php else: ?>
        <p class="muted">Aucune dépense enregistrée pour ce lot.</p>
    <?php endif; ?>

    <?php if ($operations): ?>
        <h3 class="sous-titre">Dernières opérations</h3>
        <?php foreach ($operations as $op): ?>
            <div class="op">
                <div>
                    <strong><?= e(date('d/m/Y', strtotime($op['date_op']))) ?></strong>
                    <span class="muted">
                        <?php if ($op['type'] === 'depense'): ?>
                            · Dépense, <?= e(CATEGORIES_DEPENSES[$op['categorie']] ?? $op['categorie']) ?>
                            <?= $op['libelle'] !== (CATEGORIES_DEPENSES[$op['categorie']] ?? '') ? ' : ' . e($op['libelle']) : '' ?>
                        <?php else: $p = PRODUITS_VENTE[$op['produit']] ?? ['label' => $op['produit'], 'unite' => '']; ?>
                            · Vente, <?= e($p['label']) ?> :
                            <?= e(rtrim(rtrim(number_format((float)$op['quantite'], 2, ',', ' '), '0'), ',')) ?>
                            <?= e($p['unite']) ?> × <?= e(number_format((int)$op['prix_unitaire_fcfa'], 0, ',', ' ')) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="op-droite">
                    <span class="<?= $op['type'] === 'vente' ? 'pos' : 'neg' ?>">
                        <?= $op['type'] === 'vente' ? '+' : '−' ?><?= e(number_format((int)$op['montant_fcfa'], 0, ',', ' ')) ?>
                    </span>
                    <form method="post" action="finance_supprimer.php" onsubmit="return confirm('Supprimer cette opération ?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="lot" value="<?= (int)$lot['id'] ?>">
                        <input type="hidden" name="type" value="<?= e($op['type']) ?>">
                        <input type="hidden" name="id" value="<?= (int)$op['id'] ?>">
                        <button class="btn-suppr" type="submit" title="Supprimer" aria-label="Supprimer cette opération">✕</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Derniers jours</h2>
    <?php if (!$suivis): ?>
        <p class="muted">Aucune saisie pour le moment.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Morts</th>
                        <th>Aliment (kg)</th>
                        <th><?= $estPondeuse ? 'Œufs' : 'Poids moy. (g)' ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($suivis as $s): ?>
                    <tr>
                        <td><?= e(date('d/m', strtotime($s['date_suivi']))) ?></td>
                        <td><?= (int)$s['mortalite'] ?></td>
                        <td><?= e(number_format((float)$s['aliment_kg'], 1, ',', ' ')) ?></td>
                        <td>
                            <?php
                            $val = $estPondeuse ? $s['oeufs_produits'] : $s['poids_moyen_g'];
                            echo $val === null ? '—' : (int)$val;
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<p class="centre"><a href="dashboard.php">← Retour au tableau de bord</a></p>

<script>window.LOT_DATA = <?= json_encode($donneesJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/chart.umd.js"></script>
<script src="assets/js/lot-charts.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
