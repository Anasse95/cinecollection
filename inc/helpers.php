<?php
// Fonctions utilitaires : échappement, messages flash, redirections, protection CSRF.

function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
}

function rediriger(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function lire_flashs(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// Jeton CSRF : chaque formulaire POST l'envoie, le serveur le vérifie.
function csrf_jeton(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_champ(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_jeton()) . '">';
}

function verifier_csrf(): void
{
    $jeton = $_POST['csrf'] ?? '';
    if (!is_string($jeton) || !hash_equals(csrf_jeton(), $jeton)) {
        http_response_code(400);
        die('Requête invalide (jeton CSRF). Rechargez la page et réessayez.');
    }
}

function est_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function etoiles(?int $note): string
{
    if ($note === null) {
        return '<span class="muted">Pas noté</span>';
    }
    return '<span class="stars">' . str_repeat('★', $note) . str_repeat('☆', 5 - $note) . '</span>';
}

function libelle_statut(string $statut): string
{
    return $statut === 'vu' ? 'Vu' : 'À voir';
}

// Une URL d'affiche n'est acceptée que si elle commence par http:// ou https://
function url_valide(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('#^https?://#i', $url) === 1;
}

// Affiche du film, ou à défaut une affiche générée avec le titre (couleur tirée du titre)
function affiche_html(?string $url, string $titre, int $annee, string $classe): string
{
    if ($url !== null && $url !== '') {
        return '<img class="' . $classe . '" src="' . e($url) . '" alt="Affiche de ' . e($titre) . '" loading="lazy">';
    }
    $teinte = crc32($titre) % 360;
    return '<div class="' . $classe . ' poster-fallback" style="--h:' . $teinte . '" role="img" aria-label="' . e($titre) . '">'
        . '<span class="pf-title">' . e($titre) . '</span><span class="pf-year">' . $annee . '</span></div>';
}

// Règles du mot de passe : au moins 8 caractères, une lettre et un chiffre.
function erreurs_mot_de_passe(string $mdp): array
{
    $erreurs = [];
    if (mb_strlen($mdp) < 8) {
        $erreurs[] = 'Le mot de passe doit faire au moins 8 caractères.';
    }
    if (!preg_match('/[A-Za-z]/', $mdp) || !preg_match('/\d/', $mdp)) {
        $erreurs[] = 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }
    return $erreurs;
}

function pseudo_valide(string $pseudo): bool
{
    return preg_match('/^[A-Za-z0-9_-]{3,30}$/', $pseudo) === 1;
}
