<?php
require_once __DIR__ . '/includes/finances.php';
exiger_connexion();

$lot = lot_autorise((int)($_GET['lot'] ?? $_POST['lot'] ?? 0));
if (!$lot) {
    flash('erreur', 'Lot introuvable.');
    redirect('dashboard.php');
}

$erreurs = [];
$v = ['date' => date('Y-m-d'), 'categorie' => 'aliment', 'libelle' => '', 'montant' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['date']      = trim($_POST['date'] ?? '');
    $v['categorie'] = $_POST['categorie'] ?? '';
    $v['libelle']   = trim($_POST['libelle'] ?? '');
    $v['montant']   = trim($_POST['montant'] ?? '');

    if (!date_valide($v['date']))                      $erreurs[] = 'Date invalide.';
    elseif ($v['date'] > date('Y-m-d'))                $erreurs[] = 'La date ne peut pas être dans le futur.';
    if (!isset(CATEGORIES_DEPENSES[$v['categorie']]))  $erreurs[] = 'Catégorie invalide.';
    $montant = montant_entier($v['montant']);
    if ($montant === null)                             $erreurs[] = 'Le montant doit être un nombre entier de FCFA supérieur à 0.';

    if (!$erreurs) {
        $libelle = $v['libelle'] !== '' ? mb_substr($v['libelle'], 0, 150) : CATEGORIES_DEPENSES[$v['categorie']];
        db()->prepare(
            'INSERT INTO depenses (lot_id, date_depense, categorie, libelle, montant_fcfa) VALUES (?,?,?,?,?)'
        )->execute([$lot['id'], $v['date'], $v['categorie'], $libelle, $montant]);

        flash('succes', 'Dépense de ' . fcfa($montant) . ' enregistrée.');
        redirect('lot.php?id=' . (int)$lot['id'] . '#finances');
    }
}

$titre = 'Nouvelle dépense';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Nouvelle dépense</h1>
    <p class="muted"><?= e($lot['nom']) ?> · <?= e(libelle_type($lot['type_production'])) ?></p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="lot" value="<?= (int)$lot['id'] ?>">

        <label for="date">Date</label>
        <input id="date" name="date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($v['date']) ?>" required>

        <label for="categorie">Catégorie</label>
        <select id="categorie" name="categorie">
            <?php foreach (CATEGORIES_DEPENSES as $cle => $label): ?>
                <option value="<?= e($cle) ?>" <?= $v['categorie'] === $cle ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="montant">Montant (FCFA)</label>
        <input id="montant" name="montant" inputmode="numeric" value="<?= e($v['montant']) ?>" placeholder="Ex. 45000" required>

        <label for="libelle">Détail — facultatif</label>
        <input id="libelle" name="libelle" maxlength="150" value="<?= e($v['libelle']) ?>" placeholder="Ex. 3 sacs d'aliment ponte">

        <button class="btn" type="submit">Enregistrer</button>
        <a class="btn btn-secondaire" href="lot.php?id=<?= (int)$lot['id'] ?>#finances">Annuler</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
