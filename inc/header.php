<?php
// En-tête commun. Variables attendues : $pdo, $titrePage (optionnel), $pageActive (optionnel).
$moi = utilisateur_courant($pdo);
$pageActive = $pageActive ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(($titrePage ?? 'Ma collection') . ' – CinéCollection') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎬</text></svg>">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <nav class="nav">
        <a href="index.php" class="logo">Ciné<span>Collection</span></a>
        <?php if ($moi): ?>
            <button class="nav-toggle" type="button" aria-label="Menu" data-toggle-nav>☰</button>
            <div class="nav-links" id="navLinks">
                <a href="index.php" class="<?= $pageActive === 'collection' ? 'active' : '' ?>">Ma collection</a>
                <a href="film.php" class="<?= $pageActive === 'ajout' ? 'active' : '' ?>">+ Ajouter un film</a>
                <a href="membres.php" class="<?= $pageActive === 'membres' ? 'active' : '' ?>">Membres</a>
                <?php if ($moi['role'] === 'admin'): ?>
                    <a href="admin.php" class="<?= $pageActive === 'admin' ? 'active' : '' ?>">Administration</a>
                <?php endif; ?>
                <a href="profil.php" class="user-badge <?= $pageActive === 'profil' ? 'active' : '' ?>">👤 <?= e($moi['username']) ?></a>
                <form action="logout.php" method="post" class="inline">
                    <?= csrf_champ() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Déconnexion</button>
                </form>
            </div>
        <?php else: ?>
            <div class="nav-links open">
                <a href="membres.php">Membres</a>
                <a href="login.php" class="<?= $pageActive === 'login' ? 'active' : '' ?>">Connexion</a>
                <a href="register.php" class="btn btn-primary btn-sm">Créer un compte</a>
            </div>
        <?php endif; ?>
    </nav>
</header>

<div class="toast-wrap" id="toastWrap">
    <?php foreach (lire_flashs() as $f): ?>
        <div class="toast toast-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
</div>
