<?php
require_once __DIR__ . '/includes/lots.php';
exiger_connexion();

$lot = lot_autorise((int)($_GET['lot'] ?? $_POST['lot'] ?? 0));
if (!$lot) {
    flash('erreur', 'Lot introuvable.');
    redirect('dashboard.php');
}

$estPondeuse = $lot['type_production'] === 'pondeuse';
$erreurs = [];
$v = [
    'date' => date('Y-m-d'), 'mortalite' => '0', 'aliment' => '',
    'eau' => '', 'oeufs' => '', 'poids' => '', 'remarque' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['date']      = trim($_POST['date'] ?? '');
    $v['mortalite'] = trim($_POST['mortalite'] ?? '');
    $v['aliment']   = num($_POST['aliment'] ?? '');
    $v['eau']       = num($_POST['eau'] ?? '');
    $v['oeufs']     = trim($_POST['oeufs'] ?? '');
    $v['poids']     = trim($_POST['poids'] ?? '');
    $v['remarque']  = trim($_POST['remarque'] ?? '');

    $d = DateTime::createFromFormat('Y-m-d', $v['date']);
    if (!$d || $d->format('Y-m-d') !== $v['date']) {
        $erreurs[] = 'Date invalide.';
    } elseif ($v['date'] < $lot['date_arrivee']) {
        $erreurs[] = "La date est antérieure à l'arrivée du lot (" . date('d/m/Y', strtotime($lot['date_arrivee'])) . ').';
    } elseif ($v['date'] > date('Y-m-d')) {
        $erreurs[] = 'La date ne peut pas être dans le futur.';
    }

    if (!ctype_digit($v['mortalite']))                          $erreurs[] = 'La mortalité doit être un nombre entier (0 si aucune).';
    if (!is_numeric($v['aliment']) || (float)$v['aliment'] < 0) $erreurs[] = "La quantité d'aliment est invalide.";
    if ($v['eau'] !== '' && (!is_numeric($v['eau']) || (float)$v['eau'] < 0)) $erreurs[] = "La quantité d'eau est invalide.";
    if ($estPondeuse && !ctype_digit($v['oeufs']))              $erreurs[] = "Le nombre d'œufs est obligatoire (0 si aucun).";
    if (!$estPondeuse && $v['poids'] !== '' && !ctype_digit($v['poids'])) $erreurs[] = 'Le poids moyen doit être un nombre entier de grammes.';

    if (!$erreurs) {
        db()->prepare(
            'INSERT INTO suivis_quotidiens
                (lot_id, date_suivi, mortalite, aliment_kg, eau_litres, oeufs_produits, poids_moyen_g, remarque)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                mortalite = VALUES(mortalite),
                aliment_kg = VALUES(aliment_kg),
                eau_litres = VALUES(eau_litres),
                oeufs_produits = VALUES(oeufs_produits),
                poids_moyen_g = VALUES(poids_moyen_g),
                remarque = VALUES(remarque)'
        )->execute([
            $lot['id'],
            $v['date'],
            (int)$v['mortalite'],
            (float)$v['aliment'],
            $v['eau'] === '' ? null : (float)$v['eau'],
            $estPondeuse ? (int)$v['oeufs'] : null,
            (!$estPondeuse && $v['poids'] !== '') ? (int)$v['poids'] : null,
            $v['remarque'] === '' ? null : mb_substr($v['remarque'], 0, 255),
        ]);

        flash('succes', 'Saisie du ' . date('d/m/Y', strtotime($v['date'])) . ' enregistrée.');
        redirect('lot.php?id=' . (int)$lot['id']);
    }
}

$titre = 'Saisie du jour';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Saisie du jour</h1>
    <p class="muted"><?= e($lot['nom']) ?> · <?= e(libelle_type($lot['type_production'])) ?></p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="lot" value="<?= (int)$lot['id'] ?>">

        <label for="date">Date</label>
        <input id="date" name="date" type="date"
               min="<?= e($lot['date_arrivee']) ?>" max="<?= date('Y-m-d') ?>"
               value="<?= e($v['date']) ?>" required>

        <label for="mortalite">Mortalité (nombre de sujets morts)</label>
        <input id="mortalite" name="mortalite" type="number" min="0" inputmode="numeric" value="<?= e($v['mortalite']) ?>" required>

        <label for="aliment">Aliment distribué (kg)</label>
        <input id="aliment" name="aliment" inputmode="decimal" value="<?= e($v['aliment']) ?>" required>

        <?php if ($estPondeuse): ?>
            <label for="oeufs">Œufs récoltés</label>
            <input id="oeufs" name="oeufs" type="number" min="0" inputmode="numeric" value="<?= e($v['oeufs']) ?>" required>
        <?php else: ?>
            <label for="poids">Poids moyen (g) — si pesée effectuée</label>
            <input id="poids" name="poids" type="number" min="0" inputmode="numeric" value="<?= e($v['poids']) ?>">
        <?php endif; ?>

        <label for="eau">Eau consommée (litres) — facultatif</label>
        <input id="eau" name="eau" inputmode="decimal" value="<?= e($v['eau']) ?>">

        <label for="remarque">Remarque — facultatif</label>
        <input id="remarque" name="remarque" maxlength="255" value="<?= e($v['remarque']) ?>" placeholder="Ex. vaccin, chaleur, changement d'aliment">

        <p class="muted">Si une saisie existe déjà pour cette date, elle sera remplacée.</p>

        <button class="btn" type="submit">Enregistrer</button>
        <a class="btn btn-secondaire" href="lot.php?id=<?= (int)$lot['id'] ?>">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
