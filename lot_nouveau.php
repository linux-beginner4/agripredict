<?php
require_once __DIR__ . '/includes/lots.php';
exiger_connexion();

$elevage = elevage_courant();
if (!$elevage) {
    flash('erreur', "Aucun élevage associé à ce compte.");
    redirect('dashboard.php');
}

$erreurs = [];
$v = ['nom' => '', 'type' => 'pondeuse', 'effectif' => '', 'date' => date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['nom']      = trim($_POST['nom'] ?? '');
    $v['type']     = $_POST['type'] ?? '';
    $v['effectif'] = trim($_POST['effectif'] ?? '');
    $v['date']     = trim($_POST['date'] ?? '');

    if ($v['nom'] === '')                                   $erreurs[] = 'Le nom du lot est obligatoire.';
    if (!in_array($v['type'], ['chair', 'pondeuse'], true)) $erreurs[] = 'Type de production invalide.';
    if (!ctype_digit($v['effectif']) || (int)$v['effectif'] < 1) $erreurs[] = "L'effectif doit être un nombre entier positif.";

    $d = DateTime::createFromFormat('Y-m-d', $v['date']);
    if (!$d || $d->format('Y-m-d') !== $v['date'])          $erreurs[] = "Date d'arrivée invalide.";
    elseif ($v['date'] > date('Y-m-d'))                     $erreurs[] = "La date d'arrivée ne peut pas être dans le futur.";

    if (!$erreurs) {
        db()->prepare(
            'INSERT INTO lots (elevage_id, nom, type_production, effectif_initial, date_arrivee)
             VALUES (?,?,?,?,?)'
        )->execute([$elevage['id'], $v['nom'], $v['type'], (int)$v['effectif'], $v['date']]);

        flash('succes', 'Lot créé. Vous pouvez saisir le suivi du jour.');
        redirect('lot.php?id=' . (int)db()->lastInsertId());
    }
}

$titre = 'Nouveau lot';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Nouveau lot</h1>
    <p class="muted">Un lot, c'est une bande de volaille arrivée ensemble (ex. « Pondeuses bâtiment A »).</p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>

        <label for="nom">Nom du lot</label>
        <input id="nom" name="nom" value="<?= e($v['nom']) ?>" required>

        <label for="type">Type de production</label>
        <select id="type" name="type">
            <option value="pondeuse" <?= $v['type'] === 'pondeuse' ? 'selected' : '' ?>>Pondeuses (œufs)</option>
            <option value="chair"    <?= $v['type'] === 'chair'    ? 'selected' : '' ?>>Poulets de chair</option>
        </select>

        <label for="effectif">Effectif initial (nombre de sujets)</label>
        <input id="effectif" name="effectif" type="number" min="1" inputmode="numeric" value="<?= e($v['effectif']) ?>" required>

        <label for="date">Date d'arrivée</label>
        <input id="date" name="date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($v['date']) ?>" required>

        <button class="btn" type="submit">Créer le lot</button>
        <a class="btn btn-secondaire" href="dashboard.php">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
