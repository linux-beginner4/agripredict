<?php
require_once __DIR__ . '/includes/finances.php';
exiger_connexion();

$lot = lot_autorise((int)($_GET['lot'] ?? $_POST['lot'] ?? 0));
if (!$lot) {
    flash('erreur', 'Lot introuvable.');
    redirect('dashboard.php');
}

$produits = produits_pour_lot($lot);
$erreurs = [];
$v = [
    'date' => date('Y-m-d'), 'produit' => array_key_first($produits), 'quantite' => '', 'prix' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['date']     = trim($_POST['date'] ?? '');
    $v['produit']  = $_POST['produit'] ?? '';
    $v['quantite'] = num($_POST['quantite'] ?? '');
    $v['prix']     = trim($_POST['prix'] ?? '');

    if (!date_valide($v['date']))                  $erreurs[] = 'Date invalide.';
    elseif ($v['date'] > date('Y-m-d'))            $erreurs[] = 'La date ne peut pas être dans le futur.';
    elseif ($v['date'] < $lot['date_arrivee'])     $erreurs[] = "La date est antérieure à l'arrivée du lot (" . date('d/m/Y', strtotime($lot['date_arrivee'])) . ').';
    if (!isset($produits[$v['produit']]))          $erreurs[] = 'Produit invalide.';
    if (!is_numeric($v['quantite']) || (float)$v['quantite'] <= 0) $erreurs[] = 'La quantité doit être supérieure à 0.';
    $prix = montant_entier($v['prix']);
    if ($prix === null)                            $erreurs[] = 'Le prix unitaire doit être un nombre entier de FCFA supérieur à 0.';

    if (!$erreurs) {
        $q = round((float)$v['quantite'], 2);
        $montant = (int)round($q * $prix);
        db()->prepare(
            'INSERT INTO ventes (lot_id, date_vente, produit, quantite, prix_unitaire_fcfa, montant_fcfa)
             VALUES (?,?,?,?,?,?)'
        )->execute([$lot['id'], $v['date'], $v['produit'], $q, $prix, $montant]);

        flash('succes', 'Vente de ' . fcfa($montant) . ' enregistrée.');
        redirect('lot.php?id=' . (int)$lot['id'] . '#finances');
    }
}

$titre = 'Nouvelle vente';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Nouvelle vente</h1>
    <p class="muted"><?= e($lot['nom']) ?> · <?= e(libelle_type($lot['type_production'])) ?></p>

    <?php foreach ($erreurs as $er): ?>
        <div class="alert alert-erreur"><?= e($er) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="lot" value="<?= (int)$lot['id'] ?>">

        <label for="date">Date</label>
        <input id="date" name="date" type="date" min="<?= e($lot['date_arrivee']) ?>" max="<?= date('Y-m-d') ?>" value="<?= e($v['date']) ?>" required>

        <label for="produit">Produit vendu</label>
        <select id="produit" name="produit">
            <?php foreach ($produits as $cle => $p): ?>
                <option value="<?= e($cle) ?>" data-unite="<?= e($p['unite']) ?>" <?= $v['produit'] === $cle ? 'selected' : '' ?>><?= e($p['label']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="quantite">Quantité (<span id="unite"><?= e($produits[$v['produit']]['unite'] ?? '') ?></span>)</label>
        <input id="quantite" name="quantite" inputmode="decimal" value="<?= e($v['quantite']) ?>" required>

        <label for="prix">Prix unitaire (FCFA)</label>
        <input id="prix" name="prix" inputmode="numeric" value="<?= e($v['prix']) ?>" placeholder="Ex. 2400" required>

        <p class="muted">Montant total : <strong id="total">—</strong></p>

        <button class="btn" type="submit">Enregistrer</button>
        <a class="btn btn-secondaire" href="lot.php?id=<?= (int)$lot['id'] ?>#finances">Annuler</a>
    </form>
</div>
<script>
(function () {
    var produit = document.getElementById('produit'), unite = document.getElementById('unite'),
        q = document.getElementById('quantite'), p = document.getElementById('prix'), total = document.getElementById('total');
    function maj() {
        var opt = produit.options[produit.selectedIndex];
        unite.textContent = opt ? opt.getAttribute('data-unite') : '';
        var qq = parseFloat(String(q.value).replace(',', '.')), pp = parseInt(String(p.value).replace(/\s/g, ''), 10);
        total.textContent = (qq > 0 && pp > 0) ? Math.round(qq * pp).toLocaleString('fr-FR') + ' FCFA' : '—';
    }
    [produit, q, p].forEach(function (el) { el.addEventListener('input', maj); });
    maj();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
