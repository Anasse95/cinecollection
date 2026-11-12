<?php
require __DIR__ . '/inc/bootstrap.php';

if (est_post()) {
    verifier_csrf();
    supprimer_jeton_souvenir($pdo);
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('success', 'Vous êtes déconnecté.');
}
rediriger('login.php');
