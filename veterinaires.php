<?php
require_once __DIR__ . '/includes/diagnostic.php';
exiger_connexion();

$elevage = elevage_courant();
if (!$elevage) {
    flash('erreur', 'Aucun élevage associé à ce compte.');
    redirect('dashboard.php');
}

$erreurs = [];
$v = ['nom' => '', 'tel' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'supprimer') {
        db()->prepare('DELETE FROM contacts_veterinaires WHERE id = ? AND elevage_id = ?')
            ->execute([(int)($_POST['id'] ?? 0), $elevage['id']]);
        flash('succes', 'Contact supprimé.');
        redirect('veterinaires.php');
    }

    $v['nom'] = trim($_POST['nom'] ?? '');
    $v['tel'] = trim($_POST['tel'] ?? '');
    if ($v['nom'] === '' || mb_strlen($v['nom']) > 120)          $erreurs[] = 'Le nom est obligatoire (120 caractères maximum).';
    if (!preg_match('/^[0-9+\s().-]{6,30}$/', $v['tel']))        $erreurs[] = 'Numéro de téléphone invalide (chiffres, espaces, + . - ( ) uniquement).';

    if (!$erreurs) {
        db()->prepare('INSERT INTO contacts_veterinaires (elevage_id, nom, telephone) VALUES (?,?,?)')
            ->execute([$elevage['id'], $v['nom'], $v['tel']]);
        flash('succes', 'Contact enregistré.');
        redirect('veterinaires.php');
    }
}

try {
    $contacts = veterinaires_liste((int)$elevage['id']);
} catch (Throwable $ex) {
    $contacts = null; // migration non importée
}

$titre = 'Contacts vétérinaires';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Contacts vétérinaires</h1>
    <p class="muted">Ces numéros s'affichent avec chaque résultat d'orientation sanitaire, avec un bouton pour appeler.</p>

    <?php if ($contacts === null): ?>
        <p class="muted">Fonction non activée : importez <code>sql/migration_diagnostic.sql</code> dans phpMyAdmin.</p>
    <?php else: ?>
        <?php foreach ($contacts as $c): ?>
            <div class="op">
                <div><strong><?= e($c['nom']) ?></strong><br><span class="muted"><?= e($c['telephone']) ?></span></div>
                <form method="post" onsubmit="return confirm('Supprimer ce contact ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn-suppr" type="submit" aria-label="Supprimer ce contact">✕</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (!$contacts): ?><p class="muted">Aucun contact pour le moment.</p><?php endif; ?>

        <h2 class="sous-titre">Ajouter un contact</h2>
        <?php foreach ($erreurs as $er): ?>
            <div class="alert alert-erreur"><?= e($er) ?></div>
        <?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ajouter">
            <label for="nom">Nom (vétérinaire, clinique, service vétérinaire)</label>
            <input id="nom" name="nom" maxlength="120" value="<?= e($v['nom']) ?>" required>
            <label for="tel">Téléphone</label>
            <input id="tel" name="tel" type="tel" maxlength="30" value="<?= e($v['tel']) ?>" required>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    <?php endif; ?>
</div>
<p class="centre"><a href="diagnostic.php">← Orientation sanitaire</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
