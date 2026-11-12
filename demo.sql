-- Données de démonstration (facultatif), à importer APRÈS database.sql.
-- Comptes : demo (administrateur) et cinephile, mot de passe Demo1234 pour les deux.
USE cinecollection;
SET NAMES utf8mb4;

INSERT INTO users (id, username, email, password_hash, role, bio, is_public) VALUES
(1, 'demo', 'demo@example.com', '$2y$10$Poqr2pjV1OmaKuDnfmhGI.RyYqvWJwJHSdCu4F.9odVuJl/uEXqwG', 'admin', 'Science-fiction et grands classiques.', 1),
(2, 'cinephile', 'cinephile@example.com', '$2y$10$Poqr2pjV1OmaKuDnfmhGI.RyYqvWJwJHSdCu4F.9odVuJl/uEXqwG', 'user', 'Animation japonaise et thrillers.', 1);

INSERT INTO realisateurs (id, nom) VALUES
(1, 'Christopher Nolan'), (2, 'Denis Villeneuve'), (3, 'Hayao Miyazaki'),
(4, 'Bong Joon-ho'), (5, 'Céline Sciamma'), (6, 'Quentin Tarantino'), (7, 'Greta Gerwig');

INSERT INTO films (id, titre, annee, realisateur_id, synopsis, ajoute_par) VALUES
(1, 'Inception', 2010, 1, 'Un voleur infiltre les rêves pour y dérober des secrets.', 1),
(2, 'Interstellar', 2014, 1, 'Des astronautes cherchent une nouvelle planète pour l''humanité.', 1),
(3, 'Dune', 2021, 2, 'Paul Atréides arrive sur Arrakis, la planète la plus dangereuse de l''univers.', 1),
(4, 'Le Voyage de Chihiro', 2001, 3, 'Une fillette se retrouve piégée dans le monde des esprits.', 2),
(5, 'Parasite', 2019, 4, 'Une famille pauvre s''infiltre chez une famille riche.', 2),
(6, 'Portrait de la jeune fille en feu', 2019, 5, 'Bretagne, 1770 : une peintre doit réaliser un portrait de mariage.', 1),
(7, 'Pulp Fiction', 1994, 6, 'Plusieurs histoires de truands s''entrecroisent à Los Angeles.', 2),
(8, 'Premier Contact', 2016, 2, 'Une linguiste tente de communiquer avec des extraterrestres.', 1),
(9, 'Princesse Mononoké', 1997, 3, 'Un jeune prince se retrouve au cœur d''une guerre entre la forêt et les hommes.', 2),
(10, 'Little Women', 2019, 7, 'Quatre sœurs grandissent au lendemain de la guerre de Sécession.', 1);

-- Genres (ids de database.sql : 1 Action, 2 Animation, 3 Aventure, 4 Comédie, 6 Drame,
-- 7 Fantasy, 9 Policier, 10 Romance, 11 Science-Fiction, 12 Thriller)
INSERT INTO film_genres (film_id, genre_id) VALUES
(1, 11), (1, 12), (2, 11), (2, 6), (3, 11), (3, 3), (4, 2), (4, 7), (5, 12), (5, 6),
(6, 6), (6, 10), (7, 9), (7, 12), (8, 11), (8, 6), (9, 2), (9, 7), (10, 6), (10, 10);

INSERT INTO collection (id, user_id, film_id, statut, favori, note, avis, date_visionnage) VALUES
(1, 1, 1, 'vu', 1, 5, 'Le scénario le plus malin de Nolan, et la musique de Hans Zimmer est parfaite.', '2025-03-14'),
(2, 1, 2, 'vu', 1, 5, 'Visuellement incroyable, la scène du trou noir est inoubliable.', '2025-06-02'),
(3, 1, 3, 'vu', 0, 4, 'Superbe adaptation, vivement la suite.', '2025-09-20'),
(4, 1, 6, 'vu', 0, 4, NULL, '2026-01-11'),
(5, 1, 8, 'a_voir', 0, NULL, NULL, NULL),
(6, 1, 10, 'a_voir', 0, NULL, 'Conseillé par plusieurs amis.', NULL),
(7, 2, 4, 'vu', 1, 5, 'Le chef-d''œuvre de Miyazaki, à revoir à chaque âge.', '2024-12-25'),
(8, 2, 5, 'vu', 1, 5, 'Une palme d''or méritée, le basculement à mi-film est génial.', '2025-02-08'),
(9, 2, 7, 'vu', 0, 4, NULL, '2025-07-19'),
(10, 2, 9, 'vu', 0, 4, 'Moins connu que Chihiro mais tout aussi fort.', '2025-10-03'),
(11, 2, 1, 'a_voir', 0, NULL, NULL, NULL);

INSERT INTO commentaires (collection_id, user_id, contenu) VALUES
(1, 2, 'Il est sur ma liste, je le regarde ce week-end !'),
(7, 1, 'Entièrement d''accord, la bande originale est magnifique.');
