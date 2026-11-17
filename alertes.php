<?php
require_once __DIR__ . '/includes/alertes.php';
exiger_connexion();

$elevage = elevage_courant();
if (!$elevage) {
    flash('erreur', 'Aucun élevage associé à ce compte.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db()->prepare(
        'UPDATE alertes SET vue = 1 WHERE lot_id IN (SELECT id FROM lots WHERE elevage_id = ?)'
    )->execute([$elevage['id']]);
    flash('succes', 'Toutes les alertes sont marquées comme vues.');
    redirect('alertes.php');
}

alertes_synchroniser_elevage((int)$elevage['id']);
$alertes  = alertes_liste((int)$elevage['id']);
$nonVues  = count(array_filter($alertes, fn($a) => !$a['vue']));

$titre = 'Alertes';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Alertes</h1>
    <p class="muted">
        Détection automatique des variations inhabituelles : mortalité, ponte, consommation d'aliment, coûts.
        Les calculs reposent sur des règles statistiques simples comparant chaque jour à la période précédente.
    </p>

    <?= alertes_html($alertes) ?>

    <?php if ($nonVues > 0): ?>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-secondaire" type="submit">Tout marquer comme vu (<?= $nonVues ?>)</button>
        </form>
    <?php endif; ?>
</div>
<p class="centre"><a href="dashboard.php">← Retour au tableau de bord</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
