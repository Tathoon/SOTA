-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 01 juin 2025 à 18:08
-- Version du serveur : 8.3.0
-- Version de PHP : 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sota`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `description`, `created_at`) VALUES
(1, 'Électronique', 'Appareils et accessoires électroniques', '2025-06-01 16:29:47'),
(2, 'Textile', 'Vêtements et textiles divers', '2025-06-01 16:29:47'),
(3, 'Accessoires', 'Petits objets et gadgets', '2025-06-01 16:29:47'),
(4, 'Meubles', 'Mobilier et équipements', '2025-06-01 16:29:47'),
(5, 'Sports', 'Articles de sport et loisirs', '2025-06-01 16:29:47');

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_commande` varchar(50) NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_telephone` varchar(20) DEFAULT NULL,
  `client_adresse` text,
  `date_commande` date NOT NULL,
  `date_livraison_prevue` date DEFAULT NULL,
  `statut` enum('en_attente','confirmee','en_preparation','expediee','livree','annulee') DEFAULT 'en_attente',
  `total` decimal(10,2) DEFAULT '0.00',
  `notes` text,
  `utilisateur_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_commande` (`numero_commande`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `idx_commandes_statut` (`statut`),
  KEY `idx_commandes_date` (`date_commande`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes_fournisseurs`
--

DROP TABLE IF EXISTS `commandes_fournisseurs`;
CREATE TABLE IF NOT EXISTS `commandes_fournisseurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_commande` varchar(50) NOT NULL,
  `fournisseur_id` int NOT NULL,
  `date_commande` date NOT NULL,
  `date_livraison_prevue` date DEFAULT NULL,
  `statut` enum('en_attente','confirmee','expediee','recue','annulee') DEFAULT 'en_attente',
  `total` decimal(10,2) DEFAULT '0.00',
  `notes` text,
  `utilisateur_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_commande` (`numero_commande`),
  KEY `fournisseur_id` (`fournisseur_id`),
  KEY `utilisateur_id` (`utilisateur_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `details_commandes`
--

DROP TABLE IF EXISTS `details_commandes`;
CREATE TABLE IF NOT EXISTS `details_commandes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `sous_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `details_commandes_fournisseurs`
--

DROP TABLE IF EXISTS `details_commandes_fournisseurs`;
CREATE TABLE IF NOT EXISTS `details_commandes_fournisseurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_fournisseur_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `sous_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `commande_fournisseur_id` (`commande_fournisseur_id`),
  KEY `produit_id` (`produit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fournisseurs`
--

DROP TABLE IF EXISTS `fournisseurs`;
CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `siret` varchar(14) DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` text,
  `ville` varchar(100) DEFAULT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `delais_livraison` int DEFAULT '7',
  `conditions_paiement` text,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `siret` (`siret`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id`, `nom`, `siret`, `contact`, `telephone`, `email`, `adresse`, `ville`, `code_postal`, `delais_livraison`, `conditions_paiement`, `actif`, `created_at`) VALUES
(1, 'TextilePlus', '12345678901234', 'Jean Dupont', '04 78 00 00 00', 'contact@textileplus.fr', '123 Rue de Lyon', 'Lyon', '69000', 7, '30 jours net', 1, '2025-06-01 16:29:47'),
(2, 'Mode & Co', '12345678901235', 'Marie Martin', '01 40 00 00 00', 'info@modeco.fr', '456 Avenue de Paris', 'Paris', '75001', 7, '45 jours net', 1, '2025-06-01 16:29:47'),
(3, 'Stylerv', '12345678901236', 'Pierre Durand', '04 91 00 00 00', 'pierre@stylerv.fr', '789 Boulevard de Marseille', 'Marseille', '13000', 5, 'Comptant', 1, '2025-06-01 16:29:47'),
(4, 'ChicFabric', '12345678901237', 'Sophie Bernard', '03 20 00 00 00', 'sophie@chicfabric.fr', '321 Rue de Lille', 'Lille', '59000', 10, '60 jours net', 1, '2025-06-01 16:29:47');

-- --------------------------------------------------------

--
-- Structure de la table `livraisons`
--

DROP TABLE IF EXISTS `livraisons`;
CREATE TABLE IF NOT EXISTS `livraisons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `date_prevue` date NOT NULL,
  `date_reelle` date DEFAULT NULL,
  `transporteur` varchar(100) DEFAULT NULL,
  `numero_suivi` varchar(100) DEFAULT NULL,
  `statut` enum('planifiee','en_cours','livree','echec') DEFAULT 'planifiee',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock`
--

DROP TABLE IF EXISTS `mouvements_stock`;
CREATE TABLE IF NOT EXISTS `mouvements_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `type_mouvement` enum('entree','sortie','ajustement') NOT NULL,
  `quantite` int NOT NULL,
  `quantite_avant` int NOT NULL,
  `quantite_apres` int NOT NULL,
  `motif` varchar(200) DEFAULT NULL,
  `reference_document` varchar(100) DEFAULT NULL,
  `utilisateur_id` int DEFAULT NULL,
  `date_mouvement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `produit_id` (`produit_id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `idx_mouvements_date` (`date_mouvement`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

DROP TABLE IF EXISTS `produits`;
CREATE TABLE IF NOT EXISTS `produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text,
  `categorie_id` int DEFAULT NULL,
  `stock_actuel` int DEFAULT '0',
  `seuil_minimum` int DEFAULT '5',
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `prix_vente` decimal(10,2) NOT NULL,
  `taille` varchar(50) DEFAULT NULL,
  `couleur` varchar(50) DEFAULT NULL,
  `emplacement` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `categorie_id` (`categorie_id`),
  KEY `idx_produits_actif` (`actif`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `reference`, `nom`, `description`, `categorie_id`, `stock_actuel`, `seuil_minimum`, `prix_achat`, `prix_vente`, `taille`, `couleur`, `emplacement`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'PROD-001', 'T-shirt basique', 'T-shirt en coton 100% bio', 2, 25, 5, 8.50, 19.99, 'M', 'Blanc', 'A1-B2', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47'),
(2, 'PROD-002', 'Jean slim', 'Jean stretch confortable', 2, 8, 3, 25.00, 49.99, 'L', 'Bleu', 'A2-C1', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47'),
(3, 'PROD-003', 'Montre connectée', 'Montre sport avec GPS', 1, 0, 2, 150.00, 299.99, NULL, 'Noir', 'B1-A3', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47'),
(4, 'PROD-004', 'Baskets running', 'Chaussures de sport respirantes', 5, 45, 10, 40.00, 79.99, '42', 'Gris', 'C1-B2', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47'),
(5, 'PROD-005', 'Sac à dos', 'Sac à dos urbain 25L', 3, 12, 3, 15.00, 35.99, NULL, 'Noir', 'A3-C2', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47'),
(6, 'PROD-006', 'Casque audio', 'Casque sans fil Bluetooth', 1, 6, 2, 45.00, 89.99, NULL, 'Blanc', 'B2-A1', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47'),
(7, 'PROD-007', 'Écharpe laine', 'Écharpe 100% laine mérinos', 2, 18, 5, 12.00, 29.99, NULL, 'Rouge', 'A1-C3', 1, '2025-06-01 16:29:47', '2025-06-01 16:29:47');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `role` enum('Admin','Gérant','Commercial','Préparateur','Livreur','admin','employe','gestionnaire','preparateur','commercial','manager') DEFAULT 'employe',
  `identifiant` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifiant` (`identifiant`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `role`, `identifiant`, `mot_de_passe`, `email`, `telephone`, `actif`, `derniere_connexion`, `created_at`) VALUES
(1, 'Admin', 'Système', 'Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@sota.com', NULL, 1, '2025-06-01 17:07:26', '2025-06-01 16:29:47'),
(2, 'Dupont', 'Pierre', 'Admin', 'pierre', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pierre@sota.com', NULL, 1, NULL, '2025-06-01 16:29:47'),
(3, 'Martin', 'Sophie', 'Préparateur', 'sophie', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sophie@sota.com', NULL, 1, '2025-06-01 18:03:13', '2025-06-01 16:29:47'),
(4, 'Bernard', 'Thomas', 'Commercial', 'thomas', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'thomas@sota.com', NULL, 1, NULL, '2025-06-01 16:29:47'),
(5, 'Dubois', 'Julie', 'Gérant', 'julie', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'julie@sota.com', NULL, 1, NULL, '2025-06-01 16:29:47'),
(6, 'Moreau', 'Antoine', 'Gérant', 'gerant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gerant@sota.com', '01 23 45 67 89', 1, NULL, '2025-06-01 16:29:47'),
(7, 'Leroy', 'Camille', 'Commercial', 'commercial', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'commercial@sota.com', '01 23 45 67 90', 1, NULL, '2025-06-01 16:29:47'),
(8, 'Roux', 'Maxime', 'Préparateur', 'preparateur', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'preparateur@sota.com', '01 23 45 67 91', 1, NULL, '2025-06-01 16:29:47'),
(9, 'Petit', 'Emma', 'Livreur', 'livreur', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'livreur@sota.com', '01 23 45 67 92', 1, NULL, '2025-06-01 16:29:47');

--
-- Ajout de données de test pour les commandes
--

INSERT INTO `commandes` (`id`, `numero_commande`, `client_nom`, `client_email`, `client_telephone`, `client_adresse`, `date_commande`, `date_livraison_prevue`, `statut`, `total`, `utilisateur_id`) VALUES
(1, 'CMD-2025-001', 'Dupont Pierre', 'pierre.dupont@email.fr', '01 23 45 67 89', '123 Rue de la Paix, 75001 Paris', '2025-06-01', '2025-06-08', 'confirmee', 89.98, 4),
(2, 'CMD-2025-002', 'Martin Sophie', 'sophie.martin@email.fr', '01 23 45 67 90', '456 Avenue Victor Hugo, 69000 Lyon', '2025-05-30', '2025-06-06', 'en_preparation', 329.98, 4),
(3, 'CMD-2025-003', 'Durand Paul', 'paul.durand@email.fr', '01 23 45 67 91', '789 Boulevard Saint-Germain, 13000 Marseille', '2025-05-28', '2025-06-04', 'expediee', 49.99, 7);

--
-- Ajout de détails de commandes
--

INSERT INTO `details_commandes` (`commande_id`, `produit_id`, `quantite`, `prix_unitaire`, `sous_total`) VALUES
(1, 1, 2, 19.99, 39.98),
(1, 2, 1, 49.99, 49.99),
(2, 3, 1, 299.99, 299.99),
(2, 1, 1, 19.99, 19.99),
(2, 5, 1, 35.99, 35.99),
(3, 2, 1, 49.99, 49.99);

--
-- Ajout de mouvements de stock de test
--

INSERT INTO `mouvements_stock` (`produit_id`, `type_mouvement`, `quantite`, `quantite_avant`, `quantite_apres`, `motif`, `utilisateur_id`) VALUES
(1, 'entree', 50, 0, 50, 'Stock initial', 1),
(2, 'entree', 30, 0, 30, 'Stock initial', 1),
(3, 'entree', 15, 0, 15, 'Stock initial', 1),
(4, 'entree', 60, 0, 60, 'Stock initial', 1),
(5, 'entree', 25, 0, 25, 'Stock initial', 1),
(1, 'sortie', 3, 50, 47, 'Commandes clients', 4),
(2, 'sortie', 2, 30, 28, 'Commandes clients', 4),
(3, 'sortie', 1, 15, 14, 'Commande CMD-2025-002', 4);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
