<?php
// Chargé en premier par chaque page : configuration, base de données, session, fonctions communes.

declare(strict_types=1);

if (!file_exists(__DIR__ . '/../config.php')) {
    die('Fichier config.php manquant : copiez config.example.php en config.php.');
}
$config = require __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données. Vérifiez config.php.');
}

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

connexion_auto_depuis_cookie($pdo);
