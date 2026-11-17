<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/alertes.php';
$titre = $titre ?? 'AgriPredict CI';
$user  = utilisateur_courant();
$nbAlertes = $user ? alertes_non_vues() : 0;
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2e7d32">
    <title><?= e($titre) ?> — AgriPredict CI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">🐔 AgriPredict <span>CI</span></a>
    <?php if ($user): ?>
        <nav>
            <a href="dashboard.php">Tableau de bord</a>
            <a href="alertes.php">Alertes<?= $nbAlertes > 0 ? ' <span class="pastille">' . $nbAlertes . '</span>' : '' ?></a>
            <a href="diagnostic.php">Diagnostic</a>
            <a href="logout.php">Déconnexion</a>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
<?php flash_afficher(); ?>
