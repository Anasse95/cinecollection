<?php
// Authentification : connexion, "se souvenir de moi", rôles.

const COOKIE_SOUVENIR = 'cc_souvenir';
const DUREE_SOUVENIR = 30 * 24 * 3600; // 30 jours

function utilisateur_courant(PDO $pdo): ?array
{
    static $cache = null;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if ($cache === null || $cache['id'] !== $_SESSION['user_id']) {
        $stmt = $pdo->prepare('SELECT id, username, email, role, bio, is_public, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $cache = $stmt->fetch() ?: null;
        if ($cache === null) {
            // Le compte a été supprimé entre-temps
            unset($_SESSION['user_id']);
        }
    }
    return $cache;
}

function exiger_connexion(PDO $pdo): array
{
    $user = utilisateur_courant($pdo);
    if ($user === null) {
        flash('error', 'Connectez-vous pour accéder à cette page.');
        rediriger('login.php');
    }
    return $user;
}

function exiger_admin(PDO $pdo): array
{
    $user = exiger_connexion($pdo);
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        die('Accès réservé aux administrateurs.');
    }
    return $user;
}

function ouvrir_session(PDO $pdo, int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$userId]);
}

// --- "Se souvenir de moi" ---
// Le cookie contient selector:validator. En base, on garde le selector en clair
// et seulement le hash SHA-256 du validator : un vol de la base ne suffit pas à se connecter.

function creer_jeton_souvenir(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expire = time() + DUREE_SOUVENIR;

    $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expire)]);

    setcookie(COOKIE_SOUVENIR, $selector . ':' . $validator, [
        'expires' => $expire,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
}

function supprimer_jeton_souvenir(PDO $pdo): void
{
    $cookie = $_COOKIE[COOKIE_SOUVENIR] ?? '';
    if (is_string($cookie) && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
    }
    setcookie(COOKIE_SOUVENIR, '', ['expires' => time() - 3600, 'path' => '/']);
}

function connexion_auto_depuis_cookie(PDO $pdo): void
{
    if (!empty($_SESSION['user_id'])) {
        return;
    }
    $cookie = $_COOKIE[COOKIE_SOUVENIR] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return;
    }
    [$selector, $validator] = explode(':', $cookie, 2);

    $stmt = $pdo->prepare('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()');
    $stmt->execute([$selector]);
    $jeton = $stmt->fetch();

    if ($jeton && hash_equals($jeton['validator_hash'], hash('sha256', $validator))) {
        // Jeton valide : on le remplace par un nouveau (rotation) et on ouvre la session
        $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$jeton['id']]);
        ouvrir_session($pdo, (int)$jeton['user_id']);
        creer_jeton_souvenir($pdo, (int)$jeton['user_id']);
    } else {
        supprimer_jeton_souvenir($pdo);
    }
}
