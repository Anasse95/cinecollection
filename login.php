<?php
require __DIR__ . '/inc/bootstrap.php';

if (utilisateur_courant($pdo)) {
    rediriger('index.php');
}

const MAX_ESSAIS = 5;
const BLOCAGE_SECONDES = 300;

$erreur = '';
$identifiant = '';

if (est_post()) {
    verifier_csrf();
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mdp = $_POST['password'] ?? '';

    // Limite simple contre les essais en boucle : 5 échecs = 5 minutes de blocage
    $essais = $_SESSION['essais_connexion'] ?? ['nb' => 0, 'depuis' => time()];
    if ($essais['nb'] >= MAX_ESSAIS && time() - $essais['depuis'] < BLOCAGE_SECONDES) {
        $erreur = 'Trop de tentatives. Réessayez dans quelques minutes.';
    } else {
        if (time() - $essais['depuis'] >= BLOCAGE_SECONDES) {
            $essais = ['nb' => 0, 'depuis' => time()];
        }
        // On accepte le pseudo ou l'email
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$identifiant, $identifiant]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['password_hash'])) {
            unset($_SESSION['essais_connexion']);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($mdp, PASSWORD_DEFAULT), $user['id']]);
            }
            ouvrir_session($pdo, (int)$user['id']);
            if (!empty($_POST['remember'])) {
                creer_jeton_souvenir($pdo, (int)$user['id']);
            }
            flash('success', 'Bon retour, ' . $user['username'] . ' !');
            rediriger('index.php');
        }
        $essais['nb']++;
        $_SESSION['essais_connexion'] = $essais;
        $erreur = 'Identifiant ou mot de passe incorrect.';
    }
}

$titrePage = 'Connexion';
$pageActive = 'login';
require __DIR__ . '/inc/header.php';
?>
<main class="auth-page">
    <form method="post" class="card auth-card">
        <?= csrf_champ() ?>
        <h1>Connexion</h1>
        <p class="muted center">Accédez à votre collection de films.</p>

        <?php if ($erreur): ?>
            <div class="alert alert-error"><?= e($erreur) ?></div>
        <?php endif; ?>

        <div class="fg">
            <label for="identifiant">Pseudo ou email</label>
            <input type="text" id="identifiant" name="identifiant" value="<?= e($identifiant) ?>" required autocomplete="username" autofocus>
        </div>
        <div class="fg">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="remember" value="1"> Se souvenir de moi (30 jours)
        </label>
        <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
        <p class="center muted small">Pas encore de compte ? <a href="register.php">S'inscrire</a></p>
    </form>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
