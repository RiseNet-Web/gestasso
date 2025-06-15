-- Script d'initialisation PostgreSQL pour GestAsso

-- Création de la base de données (si elle n'existe pas déjà)
-- La base de données principale est créée par les variables d'environnement Docker

-- Extensions utiles
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Configuration de la base de données
ALTER DATABASE gestasso SET timezone TO 'Europe/Paris'; 