<?php
require_once __DIR__ . '/includes/functions.php';

if (est_connecte()) {
    redirect('dashboard.php');
}

$erreur = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $mdp   = $_POST['mot_de_passe'] ?? '';

    $st = db()->prepare('SELECT id, mot_de_passe FROM utilisateurs WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($mdp, $u['mot_de_passe'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        redirect('dashboard.php');
    }
    $erreur = 'E-mail ou mot de passe incorrect.';
}

$titre = 'Connexion';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Connexion</h1>

    <?php if ($erreur): ?>
        <div class="alert alert-erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" required autofocus>

        <label for="mot_de_passe">Mot de passe</label>
        <input id="mot_de_passe" name="mot_de_passe" type="password" required>

        <button class="btn" type="submit">Se connecter</button>
    </form>

    <p class="centre muted">Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
