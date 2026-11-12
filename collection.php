<?php
// Page publique de la collection d'un membre : films, avis et commentaires.
require __DIR__ . '/inc/bootstrap.php';
$moi = utilisateur_courant($pdo);

$stmt = $pdo->prepare('SELECT id, username, bio, is_public, created_at FROM users WHERE username = ?');
$stmt->execute([$_GET['u'] ?? '']);
$membre = $stmt->fetch();

$estMoi = $membre && $moi && (int)$moi['id'] === (int)$membre['id'];
if (!$membre || (!$membre['is_public'] && !$estMoi)) {
    http_response_code(404);
    $titrePage = 'Collection introuvable';
    require __DIR__ . '/inc/header.php';
    echo '<main class="container"><div class="empty"><p>Cette collection n\'existe pas ou n\'est pas publique.</p>'
        . '<a class="btn btn-outline" href="membres.php">Voir les membres</a></div></main>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT c.id, c.statut, c.favori, c.note, c.avis, c.date_visionnage,
        f.id AS film_id, f.titre, f.annee, f.affiche_url, r.nom AS realisateur,
        GROUP_CONCAT(g.nom ORDER BY g.nom SEPARATOR ', ') AS genres
    FROM collection c
    JOIN films f ON f.id = c.film_id
    LEFT JOIN realisateurs r ON r.id = f.realisateur_id
    LEFT JOIN film_genres fg ON fg.film_id = f.id
    LEFT JOIN genres g ON g.id = fg.genre_id
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY c.statut = 'a_voir', c.favori DESC, c.note IS NULL, c.note DESC, f.titre");
$stmt->execute([$membre['id']]);
$films = $stmt->fetchAll();

// Commentaires, regroupés par entrée de collection
$commentaires = [];
if ($films) {
    $stmt = $pdo->prepare('SELECT co.id, co.collection_id, co.contenu, co.cree_le, co.user_id, u.username
        FROM commentaires co JOIN users u ON u.id = co.user_id
        JOIN collection c ON c.id = co.collection_id
        WHERE c.user_id = ? ORDER BY co.cree_le');
    $stmt->execute([$membre['id']]);
    foreach ($stmt->fetchAll() as $com) {
        $commentaires[$com['collection_id']][] = $com;
    }
}

// Films que je possède déjà, pour masquer le bouton "Ajouter à ma liste"
$mesFilms = [];
if ($moi && !$estMoi) {
    $stmt = $pdo->prepare('SELECT film_id FROM collection WHERE user_id = ?');
    $stmt->execute([$moi['id']]);
    $mesFilms = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

$titrePage = 'Collection de ' . $membre['username'];
$pageActive = 'membres';
require __DIR__ . '/inc/header.php';
?>
<section class="hero">
    <h1>Collection de <em><?= e($membre['username']) ?></em></h1>
    <?php if ($membre['bio']): ?><p class="muted"><?= e($membre['bio']) ?></p><?php endif; ?>
    <p class="muted small">Membre depuis le <?= e(date('d/m/Y', strtotime($membre['created_at']))) ?> · <?= count($films) ?> films</p>
    <?php if ($estMoi && !$membre['is_public']): ?>
        <p class="alert alert-info">Aperçu : votre collection est privée. Rendez-la publique depuis votre <a href="profil.php">profil</a>.</p>
    <?php endif; ?>
</section>

<main class="container">
    <?php if (!$films): ?>
        <div class="empty"><p>Cette collection est vide.</p></div>
    <?php endif; ?>

    <div class="public-list">
        <?php foreach ($films as $f): ?>
            <article class="card public-item" id="film-<?= $f['id'] ?>">
                <?= affiche_html($f['affiche_url'], $f['titre'], (int)$f['annee'], 'thumb') ?>
                <div class="public-body">
                    <div class="review-head">
                        <strong><?= e($f['titre']) ?></strong> <span class="muted">(<?= (int)$f['annee'] ?>)</span>
                        <span class="badge-inline badge-<?= e($f['statut']) ?>"><?= libelle_statut($f['statut']) ?></span>
                        <?php if ($f['favori']): ?><span class="fav on small-fav" title="Favori">♥</span><?php endif; ?>
                    </div>
                    <div class="muted small">
                        <?= e($f['realisateur'] ?? 'Réalisateur inconnu') ?><?= $f['genres'] ? ' · ' . e($f['genres']) : '' ?>
                        <?php if ($f['date_visionnage']): ?> · vu le <?= e(date('d/m/Y', strtotime($f['date_visionnage']))) ?><?php endif; ?>
                    </div>
                    <?php if ($f['statut'] === 'vu'): ?><div><?= etoiles($f['note'] !== null ? (int)$f['note'] : null) ?></div><?php endif; ?>
                    <?php if ($f['avis']): ?><p class="avis"><?= nl2br(e($f['avis'])) ?></p><?php endif; ?>

                    <?php if ($moi && !$estMoi && !isset($mesFilms[(int)$f['film_id']])): ?>
                        <form action="actions.php" method="post" class="inline">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="action" value="ajouter_a_voir">
                            <input type="hidden" name="film_id" value="<?= $f['film_id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm">+ Ajouter à ma liste À voir</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($f['avis']): ?>
                        <div class="comments">
                            <?php foreach ($commentaires[$f['id']] ?? [] as $com): ?>
                                <div class="comment">
                                    <strong><?= e($com['username']) ?></strong>
                                    <span class="muted small"><?= e(date('d/m/Y H:i', strtotime($com['cree_le']))) ?></span>
                                    <p><?= nl2br(e($com['contenu'])) ?></p>
                                    <?php if ($moi && ((int)$com['user_id'] === (int)$moi['id'] || $estMoi || $moi['role'] === 'admin')): ?>
                                        <form action="actions.php" method="post" class="inline" data-confirm="Supprimer ce commentaire ?">
                                            <?= csrf_champ() ?>
                                            <input type="hidden" name="action" value="supprimer_commentaire">
                                            <input type="hidden" name="id" value="<?= $com['id'] ?>">
                                            <button type="submit" class="link-btn small">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($moi): ?>
                                <form action="actions.php#film-<?= $f['id'] ?>" method="post" class="comment-form">
                                    <?= csrf_champ() ?>
                                    <input type="hidden" name="action" value="commenter">
                                    <input type="hidden" name="collection_id" value="<?= $f['id'] ?>">
                                    <input type="text" name="contenu" maxlength="1000" placeholder="Répondre à cet avis…" required>
                                    <button type="submit" class="btn btn-primary btn-sm">Envoyer</button>
                                </form>
                            <?php else: ?>
                                <p class="small muted"><a href="login.php">Connectez-vous</a> pour commenter.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
