<?php
require __DIR__ . '/inc/bootstrap.php';
$moi = exiger_connexion($pdo);

$erreurs = [];

function mot_de_passe_correct(PDO $pdo, int $userId, string $mdp): bool
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return password_verify($mdp, (string)$stmt->fetchColumn());
}

if (est_post()) {
    verifier_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'infos') {
        $pseudo = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $public = !empty($_POST['is_public']) ? 1 : 0;

        if (!pseudo_valide($pseudo)) {
            $erreurs[] = 'Le pseudo doit faire 3 à 30 caractères : lettres, chiffres, - ou _.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'Adresse email invalide.';
        }
        if (mb_strlen($bio) > 300) {
            $erreurs[] = 'La bio fait 300 caractères maximum.';
        }
        if (!$erreurs) {
            $stmt = $pdo->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $stmt->execute([$pseudo, $email, $moi['id']]);
            foreach ($stmt->fetchAll() as $autre) {
                if (strcasecmp($autre['username'], $pseudo) === 0) {
                    $erreurs[] = 'Ce pseudo est déjà pris.';
                }
                if (strcasecmp($autre['email'], $email) === 0) {
                    $erreurs[] = 'Cette adresse email est déjà utilisée.';
                }
            }
        }
        if (!$erreurs) {
            $pdo->prepare('UPDATE users SET username = ?, email = ?, bio = ?, is_public = ? WHERE id = ?')
                ->execute([$pseudo, $email, $bio === '' ? null : $bio, $public, $moi['id']]);
            flash('success', 'Profil mis à jour.');
            rediriger('profil.php');
        }
    }

    if ($action === 'mot_de_passe') {
        $actuel = $_POST['current_password'] ?? '';
        $nouveau = $_POST['new_password'] ?? '';
        if (!mot_de_passe_correct($pdo, (int)$moi['id'], $actuel)) {
            $erreurs[] = 'Mot de passe actuel incorrect.';
        }
        $erreurs = array_merge($erreurs, erreurs_mot_de_passe($nouveau));
        if ($nouveau !== ($_POST['new_password_confirm'] ?? '')) {
            $erreurs[] = 'Les deux nouveaux mots de passe ne correspondent pas.';
        }
        if (!$erreurs) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($nouveau, PASSWORD_DEFAULT), $moi['id']]);
            // Par sécurité, on déconnecte les autres appareils
            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$moi['id']]);
            session_regenerate_id(true);
            flash('success', 'Mot de passe modifié. Vos autres appareils ont été déconnectés.');
            rediriger('profil.php');
        }
    }

    if ($action === 'deconnecter_appareils') {
        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$moi['id']]);
        flash('success', 'Tous les appareils mémorisés ont été déconnectés.');
        rediriger('profil.php');
    }

    if ($action === 'supprimer_compte') {
        $nbAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if (!mot_de_passe_correct($pdo, (int)$moi['id'], $_POST['password'] ?? '')) {
            $erreurs[] = 'Mot de passe incorrect : compte non supprimé.';
        } elseif ($moi['role'] === 'admin' && $nbAdmins <= 1) {
            $erreurs[] = 'Vous êtes le seul administrateur : nommez un autre admin avant de supprimer votre compte.';
        } else {
            supprimer_jeton_souvenir($pdo);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$moi['id']]);
            $_SESSION = [];
            session_regenerate_id(true);
            flash('success', 'Votre compte et votre collection ont été supprimés.');
            rediriger('register.php');
        }
    }
    $moi = utilisateur_courant($pdo);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM remember_tokens WHERE user_id = ? AND expires_at > NOW()');
$stmt->execute([$moi['id']]);
$nbAppareils = (int)$stmt->fetchColumn();

$titrePage = 'Mon profil';
$pageActive = 'profil';
require __DIR__ . '/inc/header.php';
?>
<main class="container narrow">
    <h1 class="page-title">Mon profil</h1>
    <p class="muted">
        Membre depuis le <?= e(date('d/m/Y', strtotime($moi['created_at']))) ?>
        <?php if ($moi['role'] === 'admin'): ?> · <span class="badge-inline badge-admin">Administrateur</span><?php endif; ?>
    </p>

    <?php if ($erreurs): ?>
        <ul class="alert alert-error">
            <?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="card">
        <?= csrf_champ() ?>
        <input type="hidden" name="action" value="infos">
        <h2>Informations</h2>
        <div class="form-row">
            <div class="fg grow">
                <label for="username">Pseudo</label>
                <input type="text" id="username" name="username" value="<?= e($moi['username']) ?>" required maxlength="30">
            </div>
            <div class="fg grow">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($moi['email']) ?>" required>
            </div>
        </div>
        <div class="fg">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="2" maxlength="300" placeholder="Vos genres préférés, vos réalisateurs fétiches…"><?= e($moi['bio']) ?></textarea>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="is_public" value="1" <?= $moi['is_public'] ? 'checked' : '' ?>>
            Collection publique : visible par tous sur la page Membres
        </label>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
    </form>

    <form method="post" class="card">
        <?= csrf_champ() ?>
        <input type="hidden" name="action" value="mot_de_passe">
        <h2>Mot de passe</h2>
        <div class="fg">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-row">
            <div class="fg grow">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" data-strength>
                <div class="strength"><div class="strength-bar" id="strengthBar"></div></div>
            </div>
            <div class="fg grow">
                <label for="new_password_confirm">Confirmer</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password">
            </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Changer le mot de passe</button></div>
    </form>

    <form method="post" class="card">
        <?= csrf_champ() ?>
        <input type="hidden" name="action" value="deconnecter_appareils">
        <h2>Appareils</h2>
        <p class="muted"><?= $nbAppareils ?> appareil(s) mémorisé(s) avec « Se souvenir de moi ».</p>
        <div class="form-actions"><button type="submit" class="btn btn-outline">Déconnecter tous mes appareils</button></div>
    </form>

    <form method="post" class="card card-danger" data-confirm="Supprimer définitivement votre compte et toute votre collection ?">
        <?= csrf_champ() ?>
        <input type="hidden" name="action" value="supprimer_compte">
        <h2>Supprimer mon compte</h2>
        <p class="muted">Votre compte, votre collection, vos avis et vos commentaires seront supprimés définitivement.</p>
        <div class="fg">
            <label for="del_password">Confirmez avec votre mot de passe</label>
            <input type="password" id="del_password" name="password" required autocomplete="current-password">
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-danger">Supprimer mon compte</button></div>
    </form>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
