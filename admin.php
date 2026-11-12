<?php
// Tableau de bord administrateur : statistiques, membres, genres, catalogue.
require __DIR__ . '/inc/bootstrap.php';
$moi = exiger_admin($pdo);

if (est_post()) {
    verifier_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    switch ($action) {
        case 'changer_role':
            if ($id === (int)$moi['id']) {
                flash('error', 'Vous ne pouvez pas modifier votre propre rôle.');
                break;
            }
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
            flash('success', 'Rôle mis à jour.');
            break;

        case 'supprimer_membre':
            if ($id === (int)$moi['id']) {
                flash('error', 'Supprimez votre propre compte depuis votre profil.');
                break;
            }
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'Membre supprimé.');
            break;

        case 'ajouter_genre':
            $nom = trim($_POST['nom'] ?? '');
            if ($nom === '' || mb_strlen($nom) > 50) {
                flash('error', 'Nom de genre invalide.');
                break;
            }
            $stmt = $pdo->prepare('INSERT IGNORE INTO genres (nom) VALUES (?)');
            $stmt->execute([$nom]);
            flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? "Genre « $nom » ajouté." : 'Ce genre existe déjà.');
            break;

        case 'supprimer_genre':
            $pdo->prepare('DELETE FROM genres WHERE id = ?')->execute([$id]);
            flash('success', 'Genre supprimé.');
            break;

        case 'supprimer_film':
            $pdo->prepare('DELETE FROM films WHERE id = ?')->execute([$id]);
            flash('success', 'Film supprimé du catalogue (et de toutes les collections).');
            break;
    }
    rediriger('admin.php');
}

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users) AS membres,
    (SELECT COUNT(*) FROM users WHERE is_public = 1) AS publics,
    (SELECT COUNT(*) FROM films) AS films,
    (SELECT COUNT(*) FROM collection) AS entrees,
    (SELECT COUNT(*) FROM collection WHERE avis IS NOT NULL) AS avis,
    (SELECT COUNT(*) FROM commentaires) AS commentaires")->fetch();

$membres = $pdo->query('SELECT u.id, u.username, u.email, u.role, u.is_public, u.created_at, u.last_login,
        COUNT(c.id) AS nb_films
    FROM users u LEFT JOIN collection c ON c.user_id = u.id
    GROUP BY u.id ORDER BY u.created_at DESC')->fetchAll();

$genres = $pdo->query('SELECT g.id, g.nom, COUNT(fg.film_id) AS nb
    FROM genres g LEFT JOIN film_genres fg ON fg.genre_id = g.id
    GROUP BY g.id ORDER BY g.nom')->fetchAll();

$topFilms = $pdo->query('SELECT f.id, f.titre, f.annee, COUNT(c.id) AS nb, ROUND(AVG(c.note), 1) AS moyenne
    FROM films f LEFT JOIN collection c ON c.film_id = f.id
    GROUP BY f.id ORDER BY nb DESC, moyenne DESC, f.titre LIMIT 15')->fetchAll();

function date_fr(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : '—';
}

$titrePage = 'Administration';
$pageActive = 'admin';
require __DIR__ . '/inc/header.php';
?>
<main class="container">
    <h1 class="page-title">Administration</h1>

    <div class="stats-bar inline-stats">
        <div class="stat"><span class="stat-value"><?= (int)$stats['membres'] ?></span><span class="stat-label">Membres</span></div>
        <div class="stat"><span class="stat-value"><?= (int)$stats['publics'] ?></span><span class="stat-label">Collections publiques</span></div>
        <div class="stat"><span class="stat-value"><?= (int)$stats['films'] ?></span><span class="stat-label">Films au catalogue</span></div>
        <div class="stat"><span class="stat-value"><?= (int)$stats['entrees'] ?></span><span class="stat-label">Entrées de collection</span></div>
        <div class="stat"><span class="stat-value"><?= (int)$stats['avis'] ?></span><span class="stat-label">Avis</span></div>
        <div class="stat"><span class="stat-value"><?= (int)$stats['commentaires'] ?></span><span class="stat-label">Commentaires</span></div>
    </div>

    <h2 class="section-title">Membres</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Pseudo</th><th>Email</th><th>Films</th><th>Inscrit le</th><th>Dernière connexion</th><th>Rôle</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($membres as $m): ?>
                <tr>
                    <td>
                        <?= e($m['username']) ?>
                        <?php if ($m['is_public']): ?><a class="small" href="collection.php?u=<?= urlencode($m['username']) ?>">(publique)</a><?php endif; ?>
                    </td>
                    <td><?= e($m['email']) ?></td>
                    <td><?= (int)$m['nb_films'] ?></td>
                    <td><?= date_fr($m['created_at']) ?></td>
                    <td><?= date_fr($m['last_login']) ?></td>
                    <td>
                        <?php if ((int)$m['id'] === (int)$moi['id']): ?>
                            <span class="badge-inline badge-admin">admin (vous)</span>
                        <?php else: ?>
                            <form method="post" class="inline">
                                <?= csrf_champ() ?>
                                <input type="hidden" name="action" value="changer_role">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <select name="role" onchange="this.form.submit()" aria-label="Rôle de <?= e($m['username']) ?>">
                                    <option value="user" <?= $m['role'] === 'user' ? 'selected' : '' ?>>membre</option>
                                    <option value="admin" <?= $m['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$m['id'] !== (int)$moi['id']): ?>
                            <form method="post" class="inline" data-confirm="Supprimer <?= e($m['username']) ?> et toute sa collection ?">
                                <?= csrf_champ() ?>
                                <input type="hidden" name="action" value="supprimer_membre">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-cols">
        <section>
            <h2 class="section-title">Genres</h2>
            <form method="post" class="filters">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="ajouter_genre">
                <input type="text" name="nom" placeholder="Nouveau genre" maxlength="50" required>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </form>
            <ul class="simple-list">
                <?php foreach ($genres as $g): ?>
                    <li>
                        <span><?= e($g['nom']) ?> <span class="muted small">(<?= (int)$g['nb'] ?> films)</span></span>
                        <form method="post" class="inline" data-confirm="Supprimer le genre <?= e($g['nom']) ?> ?">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="action" value="supprimer_genre">
                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                            <button type="submit" class="link-btn small">Supprimer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section>
            <h2 class="section-title">Films les plus présents</h2>
            <ul class="simple-list">
                <?php foreach ($topFilms as $f): ?>
                    <li>
                        <span><?= e($f['titre']) ?> <span class="muted small">(<?= (int)$f['annee'] ?>) · <?= (int)$f['nb'] ?> collection(s)<?= $f['moyenne'] !== null ? ' · ' . e((string)$f['moyenne']) . '/5' : '' ?></span></span>
                        <form method="post" class="inline" data-confirm="Supprimer « <?= e($f['titre']) ?> » du catalogue et de toutes les collections ?">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="action" value="supprimer_film">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="link-btn small">Supprimer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
