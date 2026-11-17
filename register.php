<?php
require_once __DIR__ . '/includes/functions.php';

if (est_connecte()) {
    redirect('dashboard.php');
}

$erreurs = [];
$old = ['nom' => '', 'email' => '', 'telephone' => '', 'elevage' => '', 'localite' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['nom']       = trim($_POST['nom'] ?? '');
    $old['email']     = strtolower(trim($_POST['email'] ?? ''));
    $old['telephone'] = trim($_POST['telephone'] ?? '');
    $old['elevage']   = trim($_POST['elevage'] ?? '');
    $old['localite']  = trim($_POST['localite'] ?? '');
    $mdp              = $_POST['mot_de_passe'] ?? '';
    $mdp2             = $_POST['mot_de_passe2'] ?? '';

    if ($old['nom'] === '')                                   $erreurs[] = 'Le nom est obligatoire.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $erreurs[] = 'Adresse e-mail invalide.';
    if ($old['elevage'] === '')                               $erreurs[] = "Le nom de l'élevage est obligatoire.";
    if (strlen($mdp) < 6)                                     $erreurs[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    if ($mdp !== $mdp2)                                       $erreurs[] = 'Les mots de passe ne correspondent pas.';

    if (!$erreurs) {
        $st = db()->prepare('SELECT id FROM utilisateurs WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $erreurs[] = 'Cette adresse e-mail est déjà utilisée.';
        }
    }

    if (!$erreurs) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO utilisateurs (nom, email, mot_de_passe, telephone) VALUES (?,?,?,?)')
                ->execute([$old['nom'], $old['email'], password_hash($mdp, PASSWORD_DEFAULT), $old['telephone'] ?: null]);
            $uid = (int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO elevages (utilisateur_id, nom, localite, type_elevage) VALUES (?,?,?,?)')
                ->execute([$uid, $old['elevage'], $old['localite'] ?: null, 'volaille']);

            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $erreurs[] = "Erreur lors de la création du compte.";
        }

        if (!$erreurs) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            flash('succes', 'Compte créé. Bienvenue sur AgriPredict CI !');
            redirect('dashboard.php');
        }
    }
}

$titre = 'Inscription';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Créer mon compte</h1>
    <p class="muted">Suivez votre élevage de volaille : production, dépenses, alertes et prévisions.</p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>

        <label for="nom">Votre nom</label>
        <input id="nom" name="nom" value="<?= e($old['nom']) ?>" required>

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" required>

        <label for="telephone">Téléphone (facultatif)</label>
        <input id="telephone" name="telephone" type="tel" value="<?= e($old['telephone']) ?>">

        <label for="elevage">Nom de l'élevage</label>
        <input id="elevage" name="elevage" value="<?= e($old['elevage']) ?>" required>

        <label for="localite">Localité (facultatif)</label>
        <input id="localite" name="localite" value="<?= e($old['localite']) ?>" placeholder="Ex. Abidjan, Bouaké, Yamoussoukro">

        <label for="mot_de_passe">Mot de passe</label>
        <input id="mot_de_passe" name="mot_de_passe" type="password" minlength="6" required>

        <label for="mot_de_passe2">Confirmer le mot de passe</label>
        <input id="mot_de_passe2" name="mot_de_passe2" type="password" minlength="6" required>

        <button class="btn" type="submit">Créer mon compte</button>
    </form>

    <p class="centre muted">Déjà un compte ? <a href="login.php">Se connecter</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
