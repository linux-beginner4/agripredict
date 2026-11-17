<?php
require_once __DIR__ . '/includes/vaccins.php';
exiger_connexion();

$vacc = vaccin_autorise((int)($_GET['id'] ?? $_POST['id'] ?? 0));
$lot  = $vacc ? lot_autorise((int)$vacc['lot_id']) : null;
if (!$vacc || !$lot) {
    flash('erreur', 'Vaccination introuvable.');
    redirect('dashboard.php');
}

$erreurs = [];
$v = [
    'date'    => $vacc['date_realisee'] ?: date('Y-m-d'),
    'produit' => (string)$vacc['produit'],
    'voie'    => (string)$vacc['voie'],
    'flacon'  => (string)$vacc['numero_flacon'],
    'remarque' => (string)$vacc['remarque'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['date']     = trim($_POST['date'] ?? '');
    $v['produit']  = trim($_POST['produit'] ?? '');
    $v['voie']     = $_POST['voie'] ?? '';
    $v['flacon']   = trim($_POST['flacon'] ?? '');
    $v['remarque'] = trim($_POST['remarque'] ?? '');

    if (!vaccin_date_valide($v['date']))              $erreurs[] = 'Date invalide.';
    elseif ($v['date'] > date('Y-m-d'))               $erreurs[] = 'La date ne peut pas être dans le futur.';
    elseif ($v['date'] < $lot['date_arrivee'])        $erreurs[] = "La date est antérieure à l'arrivée du lot.";
    if ($v['voie'] !== '' && !isset(VOIES_VACCIN[$v['voie']])) $erreurs[] = "Voie d'administration invalide.";

    if (!$erreurs) {
        db()->prepare(
            "UPDATE vaccinations
             SET statut = 'fait', date_realisee = ?, produit = ?, voie = ?, numero_flacon = ?, remarque = ?
             WHERE id = ? AND lot_id = ?"
        )->execute([
            $v['date'],
            $v['produit'] === '' ? null : mb_substr($v['produit'], 0, 100),
            $v['voie'] === '' ? null : $v['voie'],
            $v['flacon'] === '' ? null : mb_substr($v['flacon'], 0, 60),
            $v['remarque'] === '' ? null : mb_substr($v['remarque'], 0, 255),
            $vacc['id'], $lot['id'],
        ]);
        flash('succes', 'Vaccination « ' . $vacc['nom'] . ' » enregistrée comme faite.');
        redirect('lot.php?id=' . (int)$lot['id'] . '#vaccins');
    }
}

$titre = 'Vaccin réalisé';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Vaccin réalisé</h1>
    <p class="muted">
        <strong><?= e($vacc['nom']) ?></strong> · <?= e($lot['nom']) ?><br>
        Prévu le <?= e(date('d/m/Y', strtotime($vacc['date_prevue']))) ?>
    </p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$vacc['id'] ?>">

        <label for="date">Date de réalisation</label>
        <input id="date" name="date" type="date" min="<?= e($lot['date_arrivee']) ?>" max="<?= date('Y-m-d') ?>" value="<?= e($v['date']) ?>" required>

        <label for="produit">Produit utilisé — facultatif</label>
        <input id="produit" name="produit" maxlength="100" value="<?= e($v['produit']) ?>" placeholder="Nom commercial du vaccin">

        <label for="voie">Voie d'administration — facultatif</label>
        <select id="voie" name="voie">
            <option value="">—</option>
            <?php foreach (VOIES_VACCIN as $cle => $label): ?>
                <option value="<?= e($cle) ?>" <?= $v['voie'] === $cle ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="flacon">N° de lot du flacon — facultatif</label>
        <input id="flacon" name="flacon" maxlength="60" value="<?= e($v['flacon']) ?>" placeholder="Pour la traçabilité">

        <label for="remarque">Remarque — facultatif</label>
        <input id="remarque" name="remarque" maxlength="255" value="<?= e($v['remarque']) ?>">

        <button class="btn" type="submit">Enregistrer</button>
        <a class="btn btn-secondaire" href="lot.php?id=<?= (int)$lot['id'] ?>#vaccins">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
