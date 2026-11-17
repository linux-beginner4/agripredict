<?php
require_once __DIR__ . '/includes/finances.php';
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_check();

$lot = lot_autorise((int)($_POST['lot'] ?? 0));
if (!$lot) {
    flash('erreur', 'Lot introuvable.');
    redirect('dashboard.php');
}

$id   = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';
$table = ['depense' => 'depenses', 'vente' => 'ventes'][$type] ?? null;

if ($table && $id > 0) {
    // lot_id dans la condition : on ne supprime que les opérations du lot vérifié
    db()->prepare("DELETE FROM $table WHERE id = ? AND lot_id = ?")->execute([$id, $lot['id']]);
    flash('succes', 'Opération supprimée.');
} else {
    flash('erreur', 'Opération introuvable.');
}
redirect('lot.php?id=' . (int)$lot['id'] . '#finances');
