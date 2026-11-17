<?php
require_once __DIR__ . '/includes/alertes.php';
exiger_connexion();

$user    = utilisateur_courant();
$elevage = elevage_courant();

$lots = [];
$alertes = [];
if ($elevage) {
    alertes_synchroniser_elevage((int)$elevage['id']);
    $alertes = alertes_liste((int)$elevage['id'], null, 6);

    $st = db()->prepare(
        'SELECT id, nom, type_production, effectif_initial, date_arrivee, statut, est_demo
         FROM lots WHERE elevage_id = ? ORDER BY date_arrivee DESC'
    );
    $st->execute([$elevage['id']]);
    $lots = $st->fetchAll();
}

$titre = 'Tableau de bord';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Bonjour <?= e($user['nom']) ?> 👋</h1>
    <?php if ($elevage): ?>
        <p class="muted">
            Élevage : <strong><?= e($elevage['nom']) ?></strong>
            <?= $elevage['localite'] ? '· ' . e($elevage['localite']) : '' ?>
        </p>
    <?php endif; ?>
</div>

<?php if ($lots): ?>
<div class="card">
    <h2>Alertes récentes</h2>
    <?= alertes_html($alertes) ?>
    <a class="btn btn-secondaire" href="alertes.php">Voir toutes les alertes</a>
</div>
<?php endif; ?>

<div class="card">
    <h2>Mes lots de volaille</h2>
    <?php if (!$lots): ?>
        <p class="muted">Aucun lot pour le moment. Créez votre premier lot pour commencer le suivi.</p>
    <?php else: ?>
        <?php foreach ($lots as $l): ?>
            <a class="lot-link" href="lot.php?id=<?= (int)$l['id'] ?>">
                <strong><?= e($l['nom']) ?></strong>
                <?= $l['est_demo'] ? '<span class="badge-demo">démo</span>' : '' ?><br>
                <span class="muted">
                    <?= e(libelle_type($l['type_production'])) ?>
                    · <?= (int)$l['effectif_initial'] ?> sujets
                    · arrivée le <?= e(date('d/m/Y', strtotime($l['date_arrivee']))) ?>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <a class="btn" href="lot_nouveau.php">+ Nouveau lot</a>
    <p class="centre muted"><a href="seed_demo.php">Charger des données de démonstration</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
