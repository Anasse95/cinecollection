<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$films = [];
$totalFilms = 0; $noteMoyenne = 0; $genreFavori = "—"; $anneeRecente = "—";

if ($isLoggedIn) {
    // On récupère uniquement les films de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM films WHERE user_id = ? ORDER BY annee DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $films = $stmt->fetchAll();

    $totalFilms = count($films);
    if ($totalFilms > 0) {
        $noteMoyenne = round(array_sum(array_column($films, 'note')) / $totalFilms, 1);
        $anneeRecente = max(array_column($films, 'annee'));
        $genresCount = array_count_values(array_column($films, 'genre'));
        arsort($genresCount);
        $genreFavori = array_key_first($genresCount);
    }
}

// Messages Toast venant de actions.php
$toastMessage = ''; $toastType = 'success';
if (isset($_SESSION['success'])) { $toastMessage = $_SESSION['success']; unset($_SESSION['success']); } 
elseif (isset($_SESSION['error'])) { $toastMessage = $_SESSION['error']; $toastType = 'error'; unset($_SESSION['error']); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CinéCollection – Base de données active</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <nav>
        <a href="index.php" class="logo">Ciné<span>Collection</span></a>
        <?php if($isLoggedIn): ?>
            <div class="search-bar">
                <input type="search" id="searchInput" placeholder="Rechercher..." autocomplete="off">
                <button>&#128269;</button>
            </div>
            <div class="nav-actions">
                <div class="admin-badge">👤 <?= htmlspecialchars($_SESSION['username']) ?></div>
                <button class="btn btn-primary" onclick="openAdd()">&#43; Ajouter un film</button>
                <a href="actions.php?action=logout" class="btn btn-ghost">&#9167; Déconnexion</a>
            </div>
        <?php endif; ?>
    </nav>
</header>

<?php if(!$isLoggedIn): ?>
    <div class="modal-overlay open" style="background: var(--bg);">
        <form action="actions.php" method="POST" class="modal" style="box-shadow: 0 0 50px rgba(229,9,20,0.3);">
            <input type="hidden" name="action" value="auth">
            <input type="hidden" name="mode" id="authMode" value="login">
            
            <div class="modal-header" style="justify-content: center;">
                <h2 id="authTitle">&#128272; Connexion</h2>
            </div>
            <div class="modal-body">
                <p id="authDesc" style="text-align:center; color:var(--muted); margin-bottom:1.5rem;">Connectez-vous pour accéder à votre collection.</p>
                <div class="fg"><label>Identifiant</label><input type="text" name="username" required></div>
                <div class="fg"><label>Mot de passe</label><input type="password" name="password" required></div>
                <div class="form-actions" style="flex-direction: column; align-items: center; margin-top: 2rem;">
                    <button type="submit" id="authBtn" class="btn btn-primary" style="width: 100%; padding: 0.8rem;">Se connecter</button>
                    <a href="#" onclick="toggleAuthMode()" id="authToggleBtn" style="color: var(--muted); font-size: 0.85rem; margin-top:10px;">Pas encore de compte ? S'inscrire ici.</a>
                </div>
            </div>
        </form>
    </div>
<?php else: ?>
    <section class="hero">
        <h1>Médiathèque de <em><?= htmlspecialchars($_SESSION['username']) ?></em></h1>
    </section>

    <div class="stats-bar">
        <div class="stats-inner">
            <div class="stat-item"><span class="stat-value"><?= $totalFilms ?></span><span class="stat-label">Films</span></div>
            <div class="stat-item"><span class="stat-value"><?= $noteMoyenne ?></span><span class="stat-label">Note moyenne</span></div>
            <div class="stat-item"><span class="stat-value"><?= htmlspecialchars($genreFavori) ?></span><span class="stat-label">Genre favori</span></div>
            <div class="stat-item"><span class="stat-value"><?= $anneeRecente ?></span><span class="stat-label">Année récente</span></div>
        </div>
    </div>

    <main style="margin-top:2rem;">
        <div class="films-grid" id="grid">
            <?php foreach($films as $f): ?>
                <article class="film-card" data-titre="<?= strtolower(htmlspecialchars($f['titre'].' '.$f['realisateur'].' '.$f['genre'])) ?>">
                    <img class="card-poster" src="<?= htmlspecialchars($f['img'] ?: 'https://via.placeholder.com/300x450/1a1a24/555') ?>" alt="Affiche">
                    <div class="card-overlay">
                        <h3 style="color:white; margin-bottom: 0.5rem;"><?= htmlspecialchars($f['titre']) ?></h3>
                        <div class="ov-actions">
                            <button class="btn btn-outline btn-sm" onclick="openEdit(<?= $f['id'] ?>, '<?= addslashes($f['titre']) ?>', '<?= addslashes($f['realisateur']) ?>', <?= $f['annee'] ?>, '<?= $f['genre'] ?>', <?= $f['note'] ?>, '<?= $f['img'] ?>', '<?= addslashes($f['description']) ?>')">&#9998; Éditer</button>
                            <a href="actions.php?action=delete_film&id=<?= $f['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer définitivement ce film ?');">&#128465;</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?= htmlspecialchars($f['titre']) ?></div>
                        <div class="card-meta"><span><?= $f['annee'] ?></span><span style="color:var(--gold)"><?= str_repeat('★', $f['note']).str_repeat('☆', 5-$f['note']) ?></span></div>
                        <div class="card-genre"><?= htmlspecialchars($f['genre']) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

    <div class="modal-overlay" id="mFilm">
        <form action="actions.php" method="POST" class="modal">
            <input type="hidden" name="action" value="save_film">
            <input type="hidden" name="id" id="fId">
            <div class="modal-header">
                <h2 id="mFilmTitle">Ajouter un film</h2>
                <button type="button" class="modal-close" onclick="closeModal('mFilm')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="fg"><label>Titre *</label><input type="text" name="titre" id="fTitre" required></div>
                <div class="form-row">
                    <div class="fg"><label>Réalisateur *</label><input type="text" name="real" id="fReal" required></div>
                    <div class="fg"><label>Année *</label><input type="number" name="annee" id="fAnnee" required></div>
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label>Genre</label>
                        <select name="genre" id="fGenre">
                            <option>Action</option><option>Animation</option><option>Comédie</option>
                            <option>Drame</option><option>Fantasy</option><option>Science-Fiction</option><option>Thriller</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Note *</label>
                        <div class="star-rating" id="stars">
                            <span class="star" onclick="setRating(1)">&#9734;</span><span class="star" onclick="setRating(2)">&#9734;</span>
                            <span class="star" onclick="setRating(3)">&#9734;</span><span class="star" onclick="setRating(4)">&#9734;</span><span class="star" onclick="setRating(5)">&#9734;</span>
                        </div>
                        <input type="hidden" name="note" id="fNote" value="5" required>
                    </div>
                </div>
                <div class="fg"><label>URL Affiche</label><input type="url" name="img" id="fImg"></div>
                <div class="fg"><label>Description</label><textarea name="desc" id="fDesc" rows="3"></textarea></div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('mFilm')">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="toast-wrap" id="toastWrap"></div>
<script src="script.js"></script>

<?php if($toastMessage): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => { toast("<?= addslashes($toastMessage) ?>", "<?= $toastType ?>"); });
</script>
<?php endif; ?>

</body>
</html>