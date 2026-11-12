PROJET CINÉCOLLECTION - L2 MIASHS
Binôme : Anasse BOUYDARNE & Abdelkader CHERFAOUI

--- INSTRUCTIONS D'INSTALLATION ---

1. Importation de la base de données :
Ouvrir phpMyAdmin (ou MySQL Workbench) et importer le fichier "database.sql".
Ce fichier s'occupe de créer la base de données (cinecollection) et les deux tables (users et films).

2. Configuration :
Si vos identifiants MySQL sont différents de "root"/"root", veuillez modifier le fichier "config.php" aux lignes 4 et 5 :
$user = 'votre_identifiant'; 
$pass = 'votre_mot_de_passe';

3. Lancement :
Placer le dossier du projet dans le répertoire public du serveur local (ex: xampp/htdocs).
Accéder au projet via l'URL locale (ex: http://localhost/Projet_CineCollection...).
