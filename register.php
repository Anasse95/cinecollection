<?php
require __DIR__ . '/inc/bootstrap.php';

if (utilisateur_courant($pdo)) {
    rediriger('index.php');
}

$erreurs = [];
$pseudo = '';
$email = '';

if (est_post()) {
    verifier_csrf();
    $pseudo = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mdp = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirm'] ?? '';

    if (!pseudo_valide($pseudo)) {
        $erreurs[] = 'Le pseudo doit faire 3 à 30 caractères : lettres, chiffres, - ou _.';
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $erreurs[] = 'Adresse email invalide.';
    }
    $erreurs = array_merge($erreurs, erreurs_mot_de_passe($mdp));
    if ($mdp !== $confirmation) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$pseudo, $email]);
        foreach ($stmt->fetchAll() as $existant) {
            if (strcasecmp($existant['username'], $pseudo) === 0) {
                $erreurs[] = 'Ce pseudo est déjà pris.';
            }
            if (strcasecmp($existant['email'], $email) === 0) {
                $erreurs[] = 'Un compte existe déjà avec cette adresse email.';
            }
        }
    }

    if (!$erreurs) {
        // Le tout premier compte créé devient administrateur
        $premier = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
        $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)')
            ->execute([$pseudo, $email, password_hash($mdp, PASSWORD_DEFAULT), $premier ? 'admin' : 'user']);

        ouvrir_session($pdo, (int)$pdo->lastInsertId());
        flash('success', $premier
            ? "Bienvenue $pseudo ! Premier compte créé : vous êtes administrateur."
            : "Bienvenue $pseudo ! Votre compte est créé.");
        rediriger('index.php');
    }
}

$titrePage = 'Créer un compte';
$pageActive = 'register';
require __DIR__ . '/inc/header.php';
?>
<main class="auth-page">
    <form method="post" class="card auth-card" novalidate>
        <?= csrf_champ() ?>
        <h1>Créer un compte</h1>
        <p class="muted center">Gérez votre médiathèque et partagez vos avis.</p>

        <?php if ($erreurs): ?>
            <ul class="alert alert-error">
                <?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="fg">
            <label for="username">Pseudo</label>
            <input type="text" id="username" name="username" value="<?= e($pseudo) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_\-]{3,30}" autocomplete="username">
        </div>
        <div class="fg">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
        </div>
        <div class="fg">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" data-strength>
            <div class="strength"><div class="strength-bar" id="strengthBar"></div></div>
            <small class="muted">8 caractères minimum, avec au moins une lettre et un chiffre.</small>
        </div>
        <div class="fg">
            <label for="password_confirm">Confirmer le mot de passe</label>
            <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
        <p class="center muted small">Déjà un compte ? <a href="login.php">Se connecter</a></p>
    </form>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
