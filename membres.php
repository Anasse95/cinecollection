<?php
// Liste des membres qui ont rendu leur collection publique.
require __DIR__ . '/inc/bootstrap.php';

$membres = $pdo->query("SELECT u.username, u.bio, u.created_at,
        COUNT(c.id) AS nb_films, SUM(c.statut = 'vu') AS nb_vus, SUM(c.avis IS NOT NULL) AS nb_avis
    FROM users u LEFT JOIN collection c ON c.user_id = u.id
    WHERE u.is_public = 1
    GROUP BY u.id
    ORDER BY nb_films DESC, u.username")->fetchAll();

// Derniers avis publiés par des membres publics
$derniersAvis = $pdo->query("SELECT u.username, f.titre, f.annee, c.note, c.avis, c.modifie_le
    FROM collection c JOIN users u ON u.id = c.user_id JOIN films f ON f.id = c.film_id
    WHERE u.is_public = 1 AND c.avis IS NOT NULL
    ORDER BY c.modifie_le DESC LIMIT 6")->fetchAll();

$titrePage = 'Membres';
$pageActive = 'membres';
require __DIR__ . '/inc/header.php';
?>
<main class="container">
    <h1 class="page-title">Collections publiques</h1>
    <p class="muted">Les membres qui partagent leur médiathèque. Pour apparaître ici, activez « Collection publique » dans votre profil.</p>

    <?php if (!$membres): ?>
        <div class="empty"><p>Aucune collection publique pour le moment.</p></div>
    <?php else: ?>
        <div class="members-grid">
            <?php foreach ($membres as $m): ?>
                <a class="card member-card" href="collection.php?u=<?= urlencode($m['username']) ?>">
                    <div class="avatar"><?= e(mb_strtoupper(mb_substr($m['username'], 0, 1))) ?></div>
                    <div>
                        <strong><?= e($m['username']) ?></strong>
                        <div class="muted small"><?= (int)$m['nb_films'] ?> films · <?= (int)$m['nb_vus'] ?> vus · <?= (int)$m['nb_avis'] ?> avis</div>
                        <?php if ($m['bio']): ?><p class="small"><?= e($m['bio']) ?></p><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($derniersAvis): ?>
        <h2 class="section-title">Derniers avis</h2>
        <div class="reviews">
            <?php foreach ($derniersAvis as $a): ?>
                <div class="card review">
                    <div class="review-head">
                        <strong><?= e($a['titre']) ?></strong> <span class="muted">(<?= (int)$a['annee'] ?>)</span>
                        <?= etoiles($a['note'] !== null ? (int)$a['note'] : null) ?>
                    </div>
                    <p><?= nl2br(e(mb_strimwidth($a['avis'], 0, 280, '…'))) ?></p>
                    <a class="small" href="collection.php?u=<?= urlencode($a['username']) ?>">par <?= e($a['username']) ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
