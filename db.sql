CREATE DATABASE BaimeElRahma;
USE BaimeElRahma;


CREATE TABLE parent (
    id_parent INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    mot_de_passe VARCHAR(255),
    telephone VARCHAR(20)
);


CREATE TABLE directeur (
    id_directeur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    mot_de_passe VARCHAR(255)
);


CREATE TABLE groupes (
    id_groupe INT AUTO_INCREMENT PRIMARY KEY,
    nom_groupe VARCHAR(100),
    tranche_age VARCHAR(50)
);


CREATE TABLE enfants (
    id_enfant INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    genre VARCHAR(10),
    photo VARCHAR(255),
    date_naissance DATE,
    id_parent INT,
    FOREIGN KEY (id_parent) REFERENCES parent(id_parent) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE Enfant_groupe (
    id_enfant INT,
    id_groupe INT,
    annee_scolaire VARCHAR(9),
    PRIMARY KEY (id_enfant, id_groupe, annee_scolaire),
    FOREIGN KEY (id_enfant) REFERENCES enfants(id_enfant) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_groupe) REFERENCES groupes(id_groupe) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE absences (
    id_absence INT AUTO_INCREMENT PRIMARY KEY,
    date_ DATE,
    heure_debut TIME,
    heure_fin TIME,
    justification TEXT,
    id_enfant INT,
    FOREIGN KEY (id_enfant) REFERENCES enfants(id_enfant) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE professeur (
    id_professeur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    mot_de_passe VARCHAR(255)
);


CREATE TABLE professeurs_groupes (
    id_professeur INT,
    id_groupe INT,
    annee_scolaire VARCHAR(9),
    PRIMARY KEY (id_professeur, id_groupe, annee_scolaire),
    FOREIGN KEY (id_professeur) REFERENCES professeur(id_professeur) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_groupe) REFERENCES groupes(id_groupe) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE activite (
    id_activite INT AUTO_INCREMENT PRIMARY KEY,
    nom_activite VARCHAR(100),
    description TEXT
);

CREATE TABLE groupes_activites (
    id_groupe INT,
    id_activite INT,
    date_d_activite DATETime,
    PRIMARY KEY (id_groupe, id_activite,date_d_activite),
    FOREIGN KEY (id_groupe) REFERENCES groupes(id_groupe) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_activite) REFERENCES activite(id_activite) ON DELETE CASCADE ON UPDATE CASCADE
);
