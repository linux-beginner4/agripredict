<?php
require_once __DIR__ . '/includes/diagnostic.php';
exiger_connexion();

$elevage = elevage_courant();
if (!$elevage) {
    flash('erreur', 'Aucun élevage associé à ce compte.');
    redirect('dashboard.php');
}

// Lots de l'éleveur
$st = db()->prepare('SELECT * FROM lots WHERE elevage_id = ? ORDER BY statut, date_arrivee DESC');
$st->execute([$elevage['id']]);
$lots = $st->fetchAll();

$plats = diag_symptomes_plats();
$erreurs = [];

/* ---------- Enregistrement d'une consultation ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $lot      = lot_autorise((int)($_POST['lot'] ?? 0));
    $ids      = array_values(array_filter((array)($_POST['symptomes'] ?? []), fn($i) => is_string($i) && isset($plats[$i])));
    $touches  = $_POST['touches'] ?? '';
    $dureeBrut = trim($_POST['duree'] ?? '');

    if (!$lot)                                  $erreurs[] = 'Choisissez le lot concerné.';
    if (!$ids)                                  $erreurs[] = 'Cochez au moins un symptôme observé.';
    if (!isset(DIAG_TOUCHES[$touches]))         $erreurs[] = "Indiquez la part d'animaux touchés.";
    if ($dureeBrut !== '' && (!ctype_digit($dureeBrut) || (int)$dureeBrut > 365)) $erreurs[] = 'Le nombre de jours doit être un entier (365 maximum).';

    if (!$erreurs) {
        alertes_synchroniser($lot);
        $res = diag_analyser($ids, $touches, diag_contexte_lot($lot));
        $noms = array_map(fn($m) => $m['nom'], array_slice($res['maladies'], 0, 2));

        db()->prepare(
            'INSERT INTO diagnostics (lot_id, date_consultation, symptomes, touches, duree_jours, resultat, urgence)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $lot['id'], date('Y-m-d H:i:s'), implode(',', $ids), $touches,
            $dureeBrut === '' ? null : (int)$dureeBrut,
            $noms ? implode(' / ', $noms) : 'Aucune correspondance nette',
            $res['urgence'],
        ]);
        redirect('diagnostic.php?r=' . (int)db()->lastInsertId());
    }
}

/* ---------- Affichage d'un résultat ---------- */
$consultation = null;
if (isset($_GET['r'])) {
    $consultation = diag_autorise((int)$_GET['r']);
    if (!$consultation) {
        flash('erreur', 'Consultation introuvable.');
        redirect('diagnostic.php');
    }
    $lotC = lot_autorise((int)$consultation['lot_id']);
    $idsC = array_filter(explode(',', $consultation['symptomes']));
    $res  = diag_analyser($idsC, $consultation['touches'], diag_contexte_lot($lotC));
    $contacts = veterinaires_liste((int)$elevage['id']);
}

$lotPre = (int)($_POST['lot'] ?? $_GET['lot'] ?? ($lots[0]['id'] ?? 0));
$cochesPre = (array)($_POST['symptomes'] ?? []);

$titre = 'Orientation sanitaire';
require __DIR__ . '/includes/header.php';
?>

<?php if ($consultation): ?>

<div class="card">
    <h1>Résultat de l'orientation</h1>
    <p class="muted">
        <?= e($lotC['nom']) ?> · <?= e(date('d/m/Y à H:i', strtotime($consultation['date_consultation']))) ?>
        · <?= e(DIAG_TOUCHES[$consultation['touches']] ?? '') ?>
        <?= $consultation['duree_jours'] !== null ? ' · depuis ' . (int)$consultation['duree_jours'] . ' jour(s)' : '' ?>
    </p>

    <?php if ($res['urgence'] === 'elevee'): ?>
        <div class="urgence urgence-elevee">
            <strong>Contactez un vétérinaire aujourd'hui.</strong>
            <ul>
                <?php foreach ($res['raisons'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
            </ul>
            <p>En attendant : isolez les sujets malades, limitez les déplacements de personnes et de matériel entre les bâtiments, et ne vendez ni ne déplacez d'animaux avant l'avis du vétérinaire.</p>
        </div>
    <?php else: ?>
        <div class="urgence urgence-surveiller">
            <strong>À surveiller de près.</strong>
            Si les signes persistent plus de 24 à 48 heures, s'aggravent ou touchent d'autres sujets, contactez un vétérinaire.
        </div>
    <?php endif; ?>

    <h2>Contacts vétérinaires</h2>
    <?php if ($contacts): ?>
        <?php foreach ($contacts as $c): ?>
            <a class="btn btn-appel" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['telephone'])) ?>">
                📞 Appeler <?= e($c['nom']) ?> (<?= e($c['telephone']) ?>)
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="muted">Aucun contact enregistré. <a href="veterinaires.php">Ajoutez le numéro de votre vétérinaire</a> pour pouvoir l'appeler en un clic depuis ici.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Affections évoquées</h2>
    <?php if (!$res['maladies']): ?>
        <p class="muted">Les symptômes cochés ne correspondent clairement à aucune affection de notre base. Cela ne signifie pas que tout va bien : si le problème persiste, consultez un vétérinaire.</p>
    <?php endif; ?>

    <?php foreach ($res['maladies'] as $m): ?>
        <div class="maladie maladie-<?= e($m['gravite']) ?>">
            <h3><?= e($m['nom']) ?></h3>
            <p class="puces">
                <span class="badge-niveau badge-<?= $m['gravite'] === 'moderee' ? 'attention' : 'critique' ?>"><?= e(DIAG_GRAVITES[$m['gravite']]) ?></span>
                <span class="badge-niveau">Correspondance <?= e($m['niveau']) ?></span>
                <?php if ($m['isolement']): ?><span class="badge-niveau badge-isole">Isolement conseillé</span><?php endif; ?>
                <?php if ($m['declaration']): ?><span class="badge-niveau badge-critique">À signaler aux services vétérinaires</span><?php endif; ?>
            </p>
            <p class="muted"><?= (int)$m['nb_communs'] ?> symptôme(s) sur <?= (int)$m['nb_typiques'] ?> typiques retrouvé(s) : <?= e(implode(' ; ', $m['communs'])) ?></p>
            <p><?= e($m['conseil']) ?></p>
            <?php if ($m['vaccin']): ?><p class="muted"><strong>Vaccination :</strong> <?= e($m['vaccin']) ?></p><?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p class="note-methode">
        Aide à l'orientation uniquement, établie à partir des symptômes cochés : <strong>ce n'est pas un diagnostic</strong>
        et l'outil ne prescrit aucun traitement. Seul un vétérinaire peut confirmer une maladie.
        Base de connaissances : <?= e(DIAG_BASE_VERSION) ?>.
    </p>
    <a class="btn" href="diagnostic.php?lot=<?= (int)$lotC['id'] ?>">Nouvelle consultation</a>
    <a class="btn btn-secondaire" href="lot.php?id=<?= (int)$lotC['id'] ?>#sante">Retour au lot</a>
</div>

<?php else: ?>

<div class="card">
    <h1>Orientation sanitaire</h1>
    <p class="muted">Cochez ce que vous observez : l'application indique les affections possibles, les gestes de prudence et vous aide à joindre un vétérinaire. Elle fonctionne à partir d'une base locale, sans connexion internet.</p>

    <?php if (!$lots): ?>
        <p>Créez d'abord un lot pour pouvoir signaler des symptômes.</p>
        <a class="btn" href="lot_nouveau.php">+ Nouveau lot</a>
    <?php else: ?>
        <?php foreach ($erreurs as $er): ?>
            <div class="alert alert-erreur"><?= e($er) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <?= csrf_field() ?>

            <label for="lot">Lot concerné</label>
            <select id="lot" name="lot">
                <?php foreach ($lots as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $lotPre === (int)$l['id'] ? 'selected' : '' ?>>
                        <?= e($l['nom']) ?> (<?= e(libelle_type($l['type_production'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <?php foreach (SYMPTOMES as $groupe => $liste): ?>
                <div class="groupe-symptomes">
                    <h3><?= e($groupe) ?></h3>
                    <?php foreach ($liste as $id => $label): ?>
                        <label class="symptome">
                            <input type="checkbox" name="symptomes[]" value="<?= e($id) ?>" <?= in_array($id, $cochesPre, true) ? 'checked' : '' ?>>
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <label for="touches">Combien d'animaux sont touchés ?</label>
            <select id="touches" name="touches">
                <?php foreach (DIAG_TOUCHES as $cle => $label): ?>
                    <option value="<?= e($cle) ?>" <?= ($_POST['touches'] ?? 'quelques') === $cle ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="duree">Depuis combien de jours ? — facultatif</label>
            <input id="duree" name="duree" inputmode="numeric" value="<?= e($_POST['duree'] ?? '') ?>">

            <button class="btn" type="submit">Voir l'orientation</button>
        </form>
        <p class="note-methode">Aide à l'orientation, pas un diagnostic. En cas de mortalité brutale ou de doute, appelez directement un vétérinaire. <a href="veterinaires.php">Gérer mes contacts vétérinaires</a></p>
    <?php endif; ?>
</div>

<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
