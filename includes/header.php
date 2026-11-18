<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/alertes.php';
$titre = $titre ?? 'AgriPredict CI';
$user  = utilisateur_courant();
$nbAlertes = $user ? alertes_non_vues() : 0;
$page  = basename($_SERVER['SCRIPT_NAME']);
$actif = fn(bool $ok): string => $ok ? ' class="on"' : '';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2e14">
    <title><?= e($titre) ?> — AgriPredict CI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $user ? 'app' : 'auth' ?>">
<?php if ($user): ?>
<aside class="side">
    <a class="brand" href="dashboard.php"><i>🐔</i><b>AgriPredict <span>CI</span></b></a>
    <nav>
        <a<?= $actif(!in_array($page, ['alertes.php', 'diagnostic.php', 'veterinaires.php'], true)) ?> href="dashboard.php">
            <svg viewBox="0 0 24 24"><path d="M3 12 12 4l9 8M5 10v10h14V10"/></svg><span>Tableau de bord</span></a>
        <a<?= $actif($page === 'alertes.php') ?> href="alertes.php">
            <svg viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 6 3 7 3 7H3s3-1 3-7ZM10 20a2 2 0 0 0 4 0"/></svg>
            <span>Alertes</span><?= $nbAlertes > 0 ? '<em class="pastille">' . (int)$nbAlertes . '</em>' : '' ?></a>
        <a<?= $actif($page === 'diagnostic.php') ?> href="diagnostic.php">
            <svg viewBox="0 0 24 24"><path d="M4 12h4l2-6 4 12 2-6h4"/></svg><span>Diagnostic</span></a>
        <a<?= $actif($page === 'veterinaires.php') ?> href="veterinaires.php">
            <svg viewBox="0 0 24 24"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10Z"/></svg><span>Vétérinaires</span></a>
        <a href="logout.php">
            <svg viewBox="0 0 24 24"><path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/></svg><span>Déconnexion</span></a>
    </nav>
    <div class="user"><b><?= e(mb_strtoupper(mb_substr($user['nom'], 0, 1))) ?></b><div><?= e($user['nom']) ?><small>Éleveur</small></div></div>
</aside>
<?php else: ?>
<a class="brand brand-auth" href="index.php"><i>🐔</i><b>AgriPredict <span>CI</span></b></a>
<?php endif; ?>
<div class="page">
<main class="container">
<?php flash_afficher(); ?>
