<?php
require __DIR__ . '/inc/bootstrap.php';
$moi = exiger_connexion($pdo);

$collectionId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$entree = null;

if ($collectionId > 0) {
    $stmt = $pdo->prepare('SELECT c.*, f.titre, f.annee, f.affiche_url, f.synopsis, f.ajoute_par, r.nom AS realisateur
        FROM collection c JOIN films f ON f.id = c.film_id
        LEFT JOIN realisateurs r ON r.id = f.realisateur_id
        WHERE c.id = ? AND c.user_id = ?');
    $stmt->execute([$collectionId, $moi['id']]);
    $entree = $stmt->fetch();
    if (!$entree) {
        flash('error', 'Film introuvable dans votre collection.');
        rediriger('index.php');
    }
}

// Les infos du catalogue (titre, année, affiche…) sont partagées entre tous les membres :
// seul celui qui a ajouté le film au catalogue, ou un admin, peut les modifier.
$peutModifierCatalogue = $entree === null
    || $moi['role'] === 'admin'
    || (int)$entree['ajoute_par'] === (int)$moi['id'];

$genres = $pdo->query('SELECT id, nom FROM genres ORDER BY nom')->fetchAll();
$realisateurs = $pdo->query('SELECT nom FROM realisateurs ORDER BY nom')->fetchAll(PDO::FETCH_COLUMN);

$genresFilm = [];
if ($entree) {
    $stmt = $pdo->prepare('SELECT genre_id FROM film_genres WHERE film_id = ?');
    $stmt->execute([$entree['film_id']]);
    $genresFilm = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$v = [
    'titre' => $entree['titre'] ?? '',
    'annee' => $entree['annee'] ?? '',
    'realisateur' => $entree['realisateur'] ?? '',
    'affiche_url' => $entree['affiche_url'] ?? '',
    'synopsis' => $entree['synopsis'] ?? '',
    'genres' => $genresFilm,
    'statut' => $entree['statut'] ?? 'vu',
    'favori' => (int)($entree['favori'] ?? 0),
    'note' => $entree['note'] ?? '',
    'avis' => $entree['avis'] ?? '',
    'date_visionnage' => $entree['date_visionnage'] ?? '',
];
$erreurs = [];

function id_realisateur(PDO $pdo, string $nom): ?int
{
    if ($nom === '') {
        return null;
    }
    $pdo->prepare('INSERT IGNORE INTO realisateurs (nom) VALUES (?)')->execute([$nom]);
    $stmt = $pdo->prepare('SELECT id FROM realisateurs WHERE nom = ?');
    $stmt->execute([$nom]);
    return (int)$stmt->fetchColumn();
}

function enregistrer_genres(PDO $pdo, int $filmId, array $genreIds): void
{
    $pdo->prepare('DELETE FROM film_genres WHERE film_id = ?')->execute([$filmId]);
    $stmt = $pdo->prepare('INSERT INTO film_genres (film_id, genre_id) VALUES (?, ?)');
    foreach ($genreIds as $gid) {
        $stmt->execute([$filmId, $gid]);
    }
}

if (est_post()) {
    verifier_csrf();
    $idsGenresValides = array_map('intval', array_column($genres, 'id'));

    $v = [
        'titre' => trim($_POST['titre'] ?? ''),
        'annee' => trim($_POST['annee'] ?? ''),
        'realisateur' => trim($_POST['realisateur'] ?? ''),
        'affiche_url' => trim($_POST['affiche_url'] ?? ''),
        'synopsis' => trim($_POST['synopsis'] ?? ''),
        'genres' => array_values(array_intersect(array_map('intval', (array)($_POST['genres'] ?? [])), $idsGenresValides)),
        'statut' => ($_POST['statut'] ?? '') === 'a_voir' ? 'a_voir' : 'vu',
        'favori' => !empty($_POST['favori']) ? 1 : 0,
        'note' => trim($_POST['note'] ?? ''),
        'avis' => trim($_POST['avis'] ?? ''),
        'date_visionnage' => trim($_POST['date_visionnage'] ?? ''),
    ];

    if (!$peutModifierCatalogue) {
        // On garde les infos du catalogue telles quelles
        $v['titre'] = $entree['titre'];
        $v['annee'] = (string)$entree['annee'];
        $v['realisateur'] = $entree['realisateur'] ?? '';
        $v['affiche_url'] = $entree['affiche_url'] ?? '';
        $v['synopsis'] = $entree['synopsis'] ?? '';
        $v['genres'] = $genresFilm;
    }

    $anneeMax = (int)date('Y') + 5;
    if ($v['titre'] === '' || mb_strlen($v['titre']) > 150) {
        $erreurs[] = 'Le titre est obligatoire (150 caractères maximum).';
    }
    if (!ctype_digit($v['annee']) || (int)$v['annee'] < 1888 || (int)$v['annee'] > $anneeMax) {
        $erreurs[] = "L'année doit être comprise entre 1888 et $anneeMax.";
    }
    if (mb_strlen($v['realisateur']) > 100) {
        $erreurs[] = 'Nom du réalisateur trop long.';
    }
    if ($v['affiche_url'] !== '' && !url_valide($v['affiche_url'])) {
        $erreurs[] = "L'URL de l'affiche doit commencer par http:// ou https://.";
    }
    if ($v['note'] !== '' && (!ctype_digit($v['note']) || (int)$v['note'] < 1 || (int)$v['note'] > 5)) {
        $erreurs[] = 'La note doit être entre 1 et 5.';
    }
    if ($v['date_visionnage'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $v['date_visionnage']);
        if (!$d || $d->format('Y-m-d') !== $v['date_visionnage']) {
            $erreurs[] = 'Date de visionnage invalide.';
        }
    }
    if ($v['statut'] === 'a_voir') {
        // Un film "à voir" n'a pas encore de note, d'avis ni de date de visionnage
        $v['note'] = '';
        $v['date_visionnage'] = '';
    }

    if (!$erreurs) {
        $pdo->beginTransaction();
        try {
            $realId = id_realisateur($pdo, $v['realisateur']);
            $note = $v['note'] === '' ? null : (int)$v['note'];
            $date = $v['date_visionnage'] === '' ? null : $v['date_visionnage'];
            $avis = $v['avis'] === '' ? null : $v['avis'];
            $affiche = $v['affiche_url'] === '' ? null : $v['affiche_url'];
            $synopsis = $v['synopsis'] === '' ? null : $v['synopsis'];

            if ($entree === null) {
                // Le film existe-t-il déjà dans le catalogue ?
                $stmt = $pdo->prepare('SELECT id FROM films WHERE titre = ? AND annee = ?');
                $stmt->execute([$v['titre'], (int)$v['annee']]);
                $filmId = $stmt->fetchColumn();

                if ($filmId === false) {
                    $pdo->prepare('INSERT INTO films (titre, annee, realisateur_id, affiche_url, synopsis, ajoute_par) VALUES (?, ?, ?, ?, ?, ?)')
                        ->execute([$v['titre'], (int)$v['annee'], $realId, $affiche, $synopsis, $moi['id']]);
                    $filmId = (int)$pdo->lastInsertId();
                    enregistrer_genres($pdo, $filmId, $v['genres']);
                } else {
                    $filmId = (int)$filmId;
                    $stmt = $pdo->prepare('SELECT id FROM collection WHERE user_id = ? AND film_id = ?');
                    $stmt->execute([$moi['id'], $filmId]);
                    if ($stmt->fetchColumn() !== false) {
                        throw new RuntimeException('Ce film est déjà dans votre collection.');
                    }
                }

                $pdo->prepare('INSERT INTO collection (user_id, film_id, statut, favori, note, avis, date_visionnage) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$moi['id'], $filmId, $v['statut'], $v['favori'], $note, $avis, $date]);
                $message = 'Film ajouté à votre collection !';
            } else {
                $filmId = (int)$entree['film_id'];
                if ($peutModifierCatalogue) {
                    $stmt = $pdo->prepare('SELECT id FROM films WHERE titre = ? AND annee = ? AND id <> ?');
                    $stmt->execute([$v['titre'], (int)$v['annee'], $filmId]);
                    if ($stmt->fetchColumn() !== false) {
                        throw new RuntimeException('Un autre film du catalogue a déjà ce titre et cette année.');
                    }
                    $pdo->prepare('UPDATE films SET titre = ?, annee = ?, realisateur_id = ?, affiche_url = ?, synopsis = ? WHERE id = ?')
                        ->execute([$v['titre'], (int)$v['annee'], $realId, $affiche, $synopsis, $filmId]);
                    enregistrer_genres($pdo, $filmId, $v['genres']);
                }
                $pdo->prepare('UPDATE collection SET statut = ?, favori = ?, note = ?, avis = ?, date_visionnage = ? WHERE id = ? AND user_id = ?')
                    ->execute([$v['statut'], $v['favori'], $note, $avis, $date, $entree['id'], $moi['id']]);
                $message = 'Film modifié.';
            }
            $pdo->commit();
            flash('success', $message);
            rediriger('index.php');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $erreurs[] = $ex->getMessage();
        }
    }
}

$titrePage = $entree ? 'Modifier ' . $entree['titre'] : 'Ajouter un film';
$pageActive = $entree ? 'collection' : 'ajout';
$lectureSeule = $peutModifierCatalogue ? '' : 'readonly';
require __DIR__ . '/inc/header.php';
?>
<main class="container narrow">
    <h1 class="page-title"><?= $entree ? 'Modifier « ' . e($entree['titre']) . ' »' : 'Ajouter un film' ?></h1>

    <?php if ($erreurs): ?>
        <ul class="alert alert-error">
            <?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="card form-film">
        <?= csrf_champ() ?>
        <input type="hidden" name="id" value="<?= $collectionId ?>">

        <h2>Le film</h2>
        <?php if (!$peutModifierCatalogue): ?>
            <p class="muted small">Ce film a été ajouté au catalogue par un autre membre : seules vos informations personnelles sont modifiables.</p>
        <?php elseif (!$entree): ?>
            <p class="muted small">Si le film existe déjà dans le catalogue (même titre et même année), il est simplement ajouté à votre collection.</p>
        <?php endif; ?>

        <div class="form-row">
            <div class="fg grow">
                <label for="titre">Titre *</label>
                <input type="text" id="titre" name="titre" value="<?= e($v['titre']) ?>" required maxlength="150" <?= $lectureSeule ?>>
            </div>
            <div class="fg">
                <label for="annee">Année *</label>
                <input type="number" id="annee" name="annee" value="<?= e((string)$v['annee']) ?>" required min="1888" max="<?= (int)date('Y') + 5 ?>" <?= $lectureSeule ?>>
            </div>
        </div>
        <div class="fg">
            <label for="realisateur">Réalisateur</label>
            <input type="text" id="realisateur" name="realisateur" value="<?= e($v['realisateur']) ?>" list="listeRealisateurs" maxlength="100" <?= $lectureSeule ?>>
            <datalist id="listeRealisateurs">
                <?php foreach ($realisateurs as $nom): ?><option value="<?= e($nom) ?>"><?php endforeach; ?>
            </datalist>
        </div>
        <fieldset class="fg" <?= $peutModifierCatalogue ? '' : 'disabled' ?>>
            <legend>Genres</legend>
            <div class="chips">
                <?php foreach ($genres as $g): ?>
                    <label class="chip">
                        <input type="checkbox" name="genres[]" value="<?= $g['id'] ?>" <?= in_array((int)$g['id'], $v['genres'], true) ? 'checked' : '' ?>>
                        <span><?= e($g['nom']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="fg">
            <label for="affiche_url">URL de l'affiche</label>
            <input type="url" id="affiche_url" name="affiche_url" value="<?= e($v['affiche_url']) ?>" placeholder="https://…" <?= $lectureSeule ?>>
        </div>
        <div class="fg">
            <label for="synopsis">Synopsis</label>
            <textarea id="synopsis" name="synopsis" rows="3" <?= $lectureSeule ?>><?= e($v['synopsis']) ?></textarea>
        </div>

        <h2>Dans ma collection</h2>
        <div class="form-row">
            <div class="fg">
                <label for="statut">Statut</label>
                <select id="statut" name="statut" data-statut>
                    <option value="vu" <?= $v['statut'] === 'vu' ? 'selected' : '' ?>>Vu</option>
                    <option value="a_voir" <?= $v['statut'] === 'a_voir' ? 'selected' : '' ?>>À voir</option>
                </select>
            </div>
            <div class="fg">
                <label>&nbsp;</label>
                <label class="checkbox"><input type="checkbox" name="favori" value="1" <?= $v['favori'] ? 'checked' : '' ?>> ♥ Favori</label>
            </div>
        </div>
        <div class="si-vu">
            <div class="form-row">
                <div class="fg">
                    <label>Ma note</label>
                    <div class="star-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="note<?= $i ?>" name="note" value="<?= $i ?>" <?= (string)$v['note'] === (string)$i ? 'checked' : '' ?>>
                            <label for="note<?= $i ?>" title="<?= $i ?>/5">★</label>
                        <?php endfor; ?>
                    </div>
                    <button type="button" class="link-btn small" data-clear-note>Effacer la note</button>
                </div>
                <div class="fg">
                    <label for="date_visionnage">Vu le</label>
                    <input type="date" id="date_visionnage" name="date_visionnage" value="<?= e((string)$v['date_visionnage']) ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>
        <div class="fg">
            <label for="avis">Mon avis</label>
            <textarea id="avis" name="avis" rows="4" maxlength="5000" placeholder="Ce que vous en avez pensé, ou pourquoi vous voulez le voir…"><?= e($v['avis']) ?></textarea>
        </div>

        <div class="form-actions">
            <a href="index.php" class="btn btn-outline">Annuler</a>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>

    <?php if ($entree): ?>
        <form action="actions.php" method="post" class="danger-zone" data-confirm="Retirer ce film de votre collection ?">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="supprimer_entree">
            <input type="hidden" name="id" value="<?= $entree['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Retirer de ma collection</button>
        </form>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
