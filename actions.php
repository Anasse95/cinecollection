<?php
session_start();
require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ==========================================
// 1. INSCRIPTION ET CONNEXION
// ==========================================
if ($action === 'auth') {
    $mode = $_POST['mode']; // 'login' ou 'register'
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Veuillez remplir tous les champs.";
        header("Location: index.php"); exit;
    }

    if ($mode === 'register') {
        // Vérifier si le pseudo existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Cet identifiant est déjà pris.";
        } else {
            // Créer le compte avec un mot de passe crypté
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hash]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['success'] = "Compte créé ! Bienvenue $username.";
        }
    } elseif ($mode === 'login') {
        // Connexion
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['success'] = "Bon retour, $username !";
        } else {
            $_SESSION['error'] = "Identifiant ou mot de passe incorrect.";
        }
    }
    header("Location: index.php"); exit;
}

// DÉCONNEXION
if ($action === 'logout') {
    session_destroy();
    header("Location: index.php"); exit;
}

// ==========================================
// SÉCURITÉ : IL FAUT ÊTRE CONNECTÉ POUR LA SUITE
// ==========================================
if (!isset($_SESSION['user_id'])) { die("Accès refusé."); }
$user_id = $_SESSION['user_id'];

// ==========================================
// 2. AJOUTER OU MODIFIER UN FILM
// ==========================================
if ($action === 'save_film') {
    $id = $_POST['id'] ?? '';
    $titre = htmlspecialchars($_POST['titre']);
    $real = htmlspecialchars($_POST['real']);
    $annee = (int)$_POST['annee'];
    $genre = htmlspecialchars($_POST['genre']);
    $note = (int)$_POST['note'];
    $img = filter_var($_POST['img'], FILTER_SANITIZE_URL);
    $desc = htmlspecialchars($_POST['desc']);

    if (!empty($id)) {
        // UPDATE (On vérifie que c'est bien SON film via user_id)
        $stmt = $pdo->prepare("UPDATE films SET titre=?, realisateur=?, annee=?, genre=?, note=?, img=?, description=? WHERE id=? AND user_id=?");
        $stmt->execute([$titre, $real, $annee, $genre, $note, $img, $desc, $id, $user_id]);
        $_SESSION['success'] = "Film modifié avec succès !";
    } else {
        // INSERT
        $stmt = $pdo->prepare("INSERT INTO films (user_id, titre, realisateur, annee, genre, note, img, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $titre, $real, $annee, $genre, $note, $img, $desc]);
        $_SESSION['success'] = "Film ajouté à votre collection !";
    }
    header("Location: index.php"); exit;
}

// ==========================================
// 3. SUPPRIMER UN FILM
// ==========================================
if ($action === 'delete_film') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM films WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    $_SESSION['success'] = "Film supprimé.";
    header("Location: index.php"); exit;
}
?>