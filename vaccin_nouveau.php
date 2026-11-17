<?php
require_once __DIR__ . '/includes/vaccins.php';
exiger_connexion();

$lot = lot_autorise((int)($_GET['lot'] ?? $_POST['lot'] ?? 0));
if (!$lot) {
    flash('erreur', 'Lot introuvable.');
    redirect('dashboard.php');
}

$erreurs = [];
$v = ['nom' => '', 'date' => date('Y-m-d')];
$maxDate = date('Y-m-d', strtotime('+365 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['nom']  = trim($_POST['nom'] ?? '');
    $v['date'] = trim($_POST['date'] ?? '');

    if ($v['nom'] === '' || mb_strlen($v['nom']) > 120)  $erreurs[] = 'Le nom du vaccin est obligatoire (120 caractères maximum).';
    if (!vaccin_date_valide($v['date']))                  $erreurs[] = 'Date invalide.';
    elseif ($v['date'] < $lot['date_arrivee'])            $erreurs[] = "La date est antérieure à l'arrivée du lot (" . date('d/m/Y', strtotime($lot['date_arrivee'])) . ').';
    elseif ($v['date'] > $maxDate)                        $erreurs[] = 'La date est trop éloignée (1 an maximum).';

    if (!$erreurs) {
        $age = (int)round((strtotime($v['date']) - strtotime($lot['date_arrivee'])) / 86400);
        db()->prepare(
            'INSERT INTO vaccinations (lot_id, nom, age_jour, date_prevue, statut) VALUES (?,?,?,?,?)'
        )->execute([$lot['id'], $v['nom'], $age, $v['date'], 'prevu']);

        flash('succes', 'Vaccin ajouté au calendrier.');
        redirect('lot.php?id=' . (int)$lot['id'] . '#vaccins');
    }
}

$titre = 'Ajouter un vaccin';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Ajouter un vaccin</h1>
    <p class="muted"><?= e($lot['nom']) ?> · <?= e(libelle_type($lot['type_production'])) ?></p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="lot" value="<?= (int)$lot['id'] ?>">

        <label for="nom">Vaccin ou maladie visée</label>
        <input id="nom" name="nom" maxlength="120" value="<?= e($v['nom']) ?>" placeholder="Ex. Bronchite infectieuse" required>

        <label for="date">Date prévue</label>
        <input id="date" name="date" type="date" min="<?= e($lot['date_arrivee']) ?>" max="<?= e($maxDate) ?>" value="<?= e($v['date']) ?>" required>

        <button class="btn" type="submit">Ajouter</button>
        <a class="btn btn-secondaire" href="lot.php?id=<?= (int)$lot['id'] ?>#vaccins">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
