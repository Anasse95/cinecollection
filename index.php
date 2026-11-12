<?php
require __DIR__ . '/inc/bootstrap.php';
$moi = exiger_connexion($pdo);

// --- Filtres ---
$statut = $_GET['statut'] ?? '';
$favoris = !empty($_GET['favoris']);
$genreId = (int)($_GET['genre'] ?? 0);
$recherche = trim($_GET['q'] ?? '');
$tri = $_GET['tri'] ?? 'recent';

$tris = [
    'recent' => 'c.ajoute_le DESC',
    'titre' => 'f.titre ASC',
    'annee' => 'f.annee DESC',
    'note' => 'c.note IS NULL, c.note DESC',
];
$ordre = $tris[$tri] ?? $tris['recent'];

$where = ['c.user_id = :uid'];
$params = [':uid' => $moi['id']];
if (in_array($statut, ['vu', 'a_voir'], true)) {
    $where[] = 'c.statut = :statut';
    $params[':statut'] = $statut;
}
if ($favoris) {
    $where[] = 'c.favori = 1';
}
if ($genreId > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM film_genres fg2 WHERE fg2.film_id = f.id AND fg2.genre_id = :genre)';
    $params[':genre'] = $genreId;
}
if ($recherche !== '') {
    $where[] = '(f.titre LIKE :q1 OR r.nom LIKE :q2)';
    $params[':q1'] = '%' . $recherche . '%';
    $params[':q2'] = '%' . $recherche . '%';
}

$sql = "SELECT c.id AS collection_id, c.statut, c.favori, c.note, c.date_visionnage,
               f.id AS film_id, f.titre, f.annee, f.affiche_url, r.nom AS realisateur,
               GROUP_CONCAT(g.nom ORDER BY g.nom SEPARATOR ', ') AS genres
        FROM collection c
        JOIN films f ON f.id = c.film_id
        LEFT JOIN realisateurs r ON r.id = f.realisateur_id
        LEFT JOIN film_genres fg ON fg.film_id = f.id
        LEFT JOIN genres g ON g.id = fg.genre_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY c.id
        ORDER BY $ordre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$films = $stmt->fetchAll();

// --- Statistiques de toute la collection (sans filtres) ---
$stmt = $pdo->prepare("SELECT COUNT(*) AS total,
        SUM(statut = 'vu') AS vus, SUM(statut = 'a_voir') AS a_voir,
        SUM(favori) AS favoris, ROUND(AVG(note), 1) AS moyenne
    FROM collection WHERE user_id = ?");
$stmt->execute([$moi['id']]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT g.nom FROM collection c
    JOIN film_genres fg ON fg.film_id = c.film_id
    JOIN genres g ON g.id = fg.genre_id
    WHERE c.user_id = ? GROUP BY g.id ORDER BY COUNT(*) DESC, g.nom LIMIT 1");
$stmt->execute([$moi['id']]);
$genreFavori = $stmt->fetchColumn() ?: '—';

$genres = $pdo->query('SELECT id, nom FROM genres ORDER BY nom')->fetchAll();

function lien_filtre(array $changements): string
{
    $params = array_merge($_GET, $changements);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

$titrePage = 'Ma collection';
$pageActive = 'collection';
require __DIR__ . '/inc/header.php';
?>
<section class="hero">
    <h1>Médiathèque de <em><?= e($moi['username']) ?></em></h1>
    <?php if ($moi['is_public']): ?>
        <p class="muted">Votre collection est publique : <a href="collection.php?u=<?= urlencode($moi['username']) ?>">voir la page partagée</a></p>
    <?php endif; ?>
</section>

<div class="stats-bar">
    <div class="stat"><span class="stat-value"><?= (int)$stats['total'] ?></span><span class="stat-label">Films</span></div>
    <div class="stat"><span class="stat-value"><?= (int)$stats['vus'] ?></span><span class="stat-label">Vus</span></div>
    <div class="stat"><span class="stat-value"><?= (int)$stats['a_voir'] ?></span><span class="stat-label">À voir</span></div>
    <div class="stat"><span class="stat-value"><?= (int)$stats['favoris'] ?></span><span class="stat-label">Favoris</span></div>
    <div class="stat"><span class="stat-value"><?= $stats['moyenne'] !== null ? e((string)$stats['moyenne']) : '—' ?></span><span class="stat-label">Note moyenne</span></div>
    <div class="stat"><span class="stat-value small-value"><?= e($genreFavori) ?></span><span class="stat-label">Genre favori</span></div>
</div>

<main class="container">
    <form method="get" class="filters">
        <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="Titre ou réalisateur…">
        <select name="genre">
            <option value="0">Tous les genres</option>
            <?php foreach ($genres as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $genreId === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tri">
            <option value="recent" <?= $tri === 'recent' ? 'selected' : '' ?>>Ajout récent</option>
            <option value="titre" <?= $tri === 'titre' ? 'selected' : '' ?>>Titre A→Z</option>
            <option value="annee" <?= $tri === 'annee' ? 'selected' : '' ?>>Année</option>
            <option value="note" <?= $tri === 'note' ? 'selected' : '' ?>>Meilleure note</option>
        </select>
        <?php if ($statut !== ''): ?><input type="hidden" name="statut" value="<?= e($statut) ?>"><?php endif; ?>
        <?php if ($favoris): ?><input type="hidden" name="favoris" value="1"><?php endif; ?>
        <button type="submit" class="btn btn-outline btn-sm">Filtrer</button>
    </form>

    <div class="tabs">
        <a href="<?= e(lien_filtre(['statut' => '', 'favoris' => ''])) ?>" class="tab <?= $statut === '' && !$favoris ? 'active' : '' ?>">Tous</a>
        <a href="<?= e(lien_filtre(['statut' => 'vu', 'favoris' => ''])) ?>" class="tab <?= $statut === 'vu' ? 'active' : '' ?>">Vus</a>
        <a href="<?= e(lien_filtre(['statut' => 'a_voir', 'favoris' => ''])) ?>" class="tab <?= $statut === 'a_voir' ? 'active' : '' ?>">À voir</a>
        <a href="<?= e(lien_filtre(['statut' => '', 'favoris' => '1'])) ?>" class="tab <?= $favoris ? 'active' : '' ?>">♥ Favoris</a>
    </div>

    <?php if (!$films): ?>
        <div class="empty">
            <p>Aucun film ici pour l'instant.</p>
            <a href="film.php" class="btn btn-primary">+ Ajouter un film</a>
        </div>
    <?php else: ?>
        <div class="films-grid">
            <?php foreach ($films as $f): ?>
                <article class="film-card">
                    <a href="film.php?id=<?= $f['collection_id'] ?>" class="poster-link">
                        <?= affiche_html($f['affiche_url'], $f['titre'], (int)$f['annee'], 'card-poster') ?>
                        <span class="badge badge-<?= e($f['statut']) ?>"><?= libelle_statut($f['statut']) ?></span>
                    </a>
                    <form action="actions.php" method="post" class="fav-form">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="action" value="toggle_favori">
                        <input type="hidden" name="id" value="<?= $f['collection_id'] ?>">
                        <button type="submit" class="fav <?= $f['favori'] ? 'on' : '' ?>" title="<?= $f['favori'] ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">♥</button>
                    </form>
                    <div class="card-body">
                        <div class="card-title" title="<?= e($f['titre']) ?>"><?= e($f['titre']) ?></div>
                        <div class="card-meta"><span><?= (int)$f['annee'] ?></span><?= etoiles($f['note'] !== null ? (int)$f['note'] : null) ?></div>
                        <div class="card-sub"><?= e($f['realisateur'] ?? 'Réalisateur inconnu') ?></div>
                        <?php if ($f['genres']): ?><div class="card-genre"><?= e($f['genres']) ?></div><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
