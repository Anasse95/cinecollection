CREATE DATABASE IF NOT EXISTS cinecollection CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cinecollection;

-- On supprime les enfants AVANT les parents pour éviter l'erreur 3730
DROP TABLE IF EXISTS films;
DROP TABLE IF EXISTS users;

-- Table des utilisateurs (pour le multi-comptes)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Table des films (liée à l'utilisateur)
CREATE TABLE films (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    titre VARCHAR(100) NOT NULL,
    realisateur VARCHAR(100) NOT NULL,
    annee INT NOT NULL,
    genre VARCHAR(50),
    note INT NOT NULL,
    img VARCHAR(255),
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);