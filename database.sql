-- CinéCollection v2 : schéma de la base de données
-- Compatible MySQL 8 et MariaDB 10.4+ (XAMPP)

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS cinecollection CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cinecollection;

-- Suppression dans l'ordre inverse des dépendances
DROP TABLE IF EXISTS commentaires;
DROP TABLE IF EXISTS collection;
DROP TABLE IF EXISTS film_genres;
DROP TABLE IF EXISTS films;
DROP TABLE IF EXISTS realisateurs;
DROP TABLE IF EXISTS genres;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS users;

-- Utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    bio VARCHAR(300) NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
) ENGINE=InnoDB;

-- Jetons "Se souvenir de moi" (on ne stocke que le hash du secret)
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    validator_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Genres
CREATE TABLE genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Réalisateurs
CREATE TABLE realisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Catalogue de films partagé entre tous les utilisateurs
CREATE TABLE films (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    annee SMALLINT NOT NULL,
    realisateur_id INT NULL,
    affiche_url VARCHAR(500) NULL,
    synopsis TEXT NULL,
    ajoute_par INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_film (titre, annee),
    FOREIGN KEY (realisateur_id) REFERENCES realisateurs(id) ON DELETE SET NULL,
    FOREIGN KEY (ajoute_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Un film peut avoir plusieurs genres (relation N-N)
CREATE TABLE film_genres (
    film_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (film_id, genre_id),
    FOREIGN KEY (film_id) REFERENCES films(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Collection personnelle : statut, favori, note et avis de chaque utilisateur sur un film
CREATE TABLE collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    film_id INT NOT NULL,
    statut ENUM('vu', 'a_voir') NOT NULL DEFAULT 'a_voir',
    favori TINYINT(1) NOT NULL DEFAULT 0,
    note TINYINT NULL,
    avis TEXT NULL,
    date_visionnage DATE NULL,
    ajoute_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_collection (user_id, film_id),
    CONSTRAINT chk_note CHECK (note IS NULL OR note BETWEEN 1 AND 5),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (film_id) REFERENCES films(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Commentaires laissés sous l'avis d'un autre membre (collections publiques)
CREATE TABLE commentaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    user_id INT NOT NULL,
    contenu VARCHAR(1000) NOT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_id) REFERENCES collection(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Genres de départ
INSERT INTO genres (nom) VALUES
('Action'), ('Animation'), ('Aventure'), ('Comédie'), ('Documentaire'), ('Drame'),
('Fantasy'), ('Horreur'), ('Policier'), ('Romance'), ('Science-Fiction'), ('Thriller');
