<?php
/** Actions POST sur les vaccinations : modele (calendrier type), supprimer, annuler (repasser à « prévu ») */
require_once __DIR__ . '/includes/vaccins.php';
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_check();

$action = $_POST['action'] ?? '';

if ($action === 'modele') {
    $lot = lot_autorise((int)($_POST['lot'] ?? 0));
    if (!$lot) {
        flash('erreur', 'Lot introuvable.');
        redirect('dashboard.php');
    }
    $n = vaccins_ajouter_modele($lot);
    flash($n > 0 ? 'succes' : 'info', $n > 0
        ? "$n vaccin(s) ajouté(s). Calendrier indicatif : faites-le valider par votre vétérinaire."
        : 'Le calendrier type est déjà complet pour ce lot.');
    redirect('lot.php?id=' . (int)$lot['id'] . '#vaccins');
}

$vacc = vaccin_autorise((int)($_POST['id'] ?? 0));
if (!$vacc) {
    flash('erreur', 'Vaccination introuvable.');
    redirect('dashboard.php');
}

if ($action === 'supprimer') {
    db()->prepare('DELETE FROM vaccinations WHERE id = ? AND lot_id = ?')->execute([$vacc['id'], $vacc['lot_id']]);
    flash('succes', 'Vaccin supprimé du calendrier.');
} elseif ($action === 'annuler') {
    db()->prepare(
        "UPDATE vaccinations SET statut = 'prevu', date_realisee = NULL WHERE id = ? AND lot_id = ?"
    )->execute([$vacc['id'], $vacc['lot_id']]);
    flash('succes', 'Vaccin repassé en « prévu ».');
} else {
    flash('erreur', 'Action inconnue.');
}
redirect('lot.php?id=' . (int)$vacc['lot_id'] . '#vaccins');
