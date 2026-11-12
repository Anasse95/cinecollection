<?php
// Actions rapides envoyées en POST depuis les différentes pages.
require __DIR__ . '/inc/bootstrap.php';
$moi = exiger_connexion($pdo);

if (!est_post()) {
    rediriger('index.php');
}
verifier_csrf();

$action = $_POST['action'] ?? '';
$retour = $_SERVER['HTTP_REFERER'] ?? 'index.php';
// On ne revient que vers une page de ce site
if (parse_url($retour, PHP_URL_HOST) !== null && parse_url($retour, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) {
    $retour = 'index.php';
}

switch ($action) {
    case 'toggle_favori':
        $pdo->prepare('UPDATE collection SET favori = 1 - favori WHERE id = ? AND user_id = ?')
            ->execute([(int)($_POST['id'] ?? 0), $moi['id']]);
        rediriger($retour);
        break;

    case 'supprimer_entree':
        $pdo->prepare('DELETE FROM collection WHERE id = ? AND user_id = ?')
            ->execute([(int)($_POST['id'] ?? 0), $moi['id']]);
        flash('success', 'Film retiré de votre collection.');
        rediriger('index.php');
        break;

    case 'ajouter_a_voir':
        // Ajoute un film vu chez un autre membre dans sa propre liste "À voir"
        $filmId = (int)($_POST['film_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT titre FROM films WHERE id = ?');
        $stmt->execute([$filmId]);
        $titre = $stmt->fetchColumn();
        if ($titre !== false) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO collection (user_id, film_id, statut) VALUES (?, ?, 'a_voir')");
            $stmt->execute([$moi['id'], $filmId]);
            flash('success', $stmt->rowCount() > 0
                ? "« $titre » ajouté à votre liste À voir."
                : "« $titre » est déjà dans votre collection.");
        }
        rediriger($retour);
        break;

    case 'commenter':
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $contenu = trim($_POST['contenu'] ?? '');
        // On ne peut commenter que l'avis d'une collection publique (ou le sien)
        $stmt = $pdo->prepare('SELECT c.id FROM collection c JOIN users u ON u.id = c.user_id
            WHERE c.id = ? AND c.avis IS NOT NULL AND (u.is_public = 1 OR u.id = ?)');
        $stmt->execute([$collectionId, $moi['id']]);
        if ($stmt->fetchColumn() === false) {
            flash('error', 'Impossible de commenter cet avis.');
        } elseif ($contenu === '' || mb_strlen($contenu) > 1000) {
            flash('error', 'Le commentaire doit faire entre 1 et 1000 caractères.');
        } else {
            $pdo->prepare('INSERT INTO commentaires (collection_id, user_id, contenu) VALUES (?, ?, ?)')
                ->execute([$collectionId, $moi['id'], $contenu]);
            flash('success', 'Commentaire publié.');
        }
        rediriger($retour);
        break;

    case 'supprimer_commentaire':
        // Autorisé pour l'auteur du commentaire, le propriétaire de l'avis ou un admin
        $stmt = $pdo->prepare('SELECT co.user_id AS auteur, c.user_id AS proprietaire
            FROM commentaires co JOIN collection c ON c.id = co.collection_id WHERE co.id = ?');
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        $com = $stmt->fetch();
        $autorise = $com && (
            in_array((int)$moi['id'], [(int)$com['auteur'], (int)$com['proprietaire']], true)
            || $moi['role'] === 'admin'
        );
        if ($autorise) {
            $pdo->prepare('DELETE FROM commentaires WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('success', 'Commentaire supprimé.');
        }
        rediriger($retour);
        break;

    default:
        rediriger('index.php');
}
