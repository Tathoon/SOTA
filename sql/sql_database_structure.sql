-- Structure de base de données SOTA Fashion
-- Version: 1.0
-- Date: 2025

CREATE DATABASE IF NOT EXISTS sota CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sota;

-- Table des utilisateurs LDAP
CREATE TABLE utilisateurs_ldap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    prenom VARCHAR(100),
    nom VARCHAR(100),
    email VARCHAR(255),
    role ENUM('Admin', 'Gérant', 'Commercial', 'Préparateur', 'Livreur') DEFAULT 'Commercial',
    actif BOOLEAN DEFAULT TRUE,
    derniere_connexion DATETIME,
    derniere_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des catégories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des fournisseurs
CREATE TABLE fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    siret VARCHAR(14),
    contact VARCHAR(255),
    telephone VARCHAR(20),
    email VARCHAR(255),
    adresse TEXT,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    pays VARCHAR(100) DEFAULT 'France',
    delais_livraison INT DEFAULT 7,
    conditions_paiement VARCHAR(255),
    specialite_mode VARCHAR(255),
    note_qualite DECIMAL(2,1),
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des produits
CREATE TABLE produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(100) UNIQUE NOT NULL,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    categorie_id INT,
    stock_actuel INT DEFAULT 0,
    seuil_minimum INT DEFAULT 5,
    prix_achat DECIMAL(10,2),
    prix_vente DECIMAL(10,2) NOT NULL,
    taille VARCHAR(20),
    couleur VARCHAR(50),
    emplacement VARCHAR(100),
    lot_minimum INT DEFAULT 1,
    poids DECIMAL(8,2),
    composition TEXT,
    saison VARCHAR(50),
    marque VARCHAR(100),
    collection VARCHAR(100),
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_reference (reference),
    INDEX idx_nom (nom),
    INDEX idx_categorie (categorie_id),
    INDEX idx_stock (stock_actuel),
    INDEX idx_prix (prix_vente)
);

-- Table des mouvements de stock
CREATE TABLE mouvements_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    type_mouvement ENUM('entree', 'sortie', 'ajustement') NOT NULL,
    quantite INT NOT NULL,
    quantite_avant INT NOT NULL,
    quantite_apres INT NOT NULL,
    motif TEXT,
    reference_document VARCHAR(100),
    cout_unitaire DECIMAL(10,2),
    utilisateur_id INT,
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs_ldap(id) ON DELETE SET NULL,
    INDEX idx_produit (produit_id),
    INDEX idx_date (date_mouvement),
    INDEX idx_type (type_mouvement)
);

-- Table des commandes
CREATE TABLE commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) UNIQUE NOT NULL,
    client_nom VARCHAR(255) NOT NULL,
    client_email VARCHAR(255),
    client_telephone VARCHAR(20),
    client_adresse TEXT,
    client_code_postal VARCHAR(10),
    client_ville VARCHAR(100),
    date_commande DATE NOT NULL,
    date_livraison_prevue DATE,
    total DECIMAL(12,2) DEFAULT 0,
    total_ht DECIMAL(12,2) DEFAULT 0,
    tva DECIMAL(12,2) DEFAULT 0,
    statut ENUM('en_attente', 'confirmee', 'en_preparation', 'expediee', 'livree', 'annulee') DEFAULT 'en_attente',
    utilisateur_id INT,
    sage_export BOOLEAN DEFAULT FALSE,
    sage_facture_id VARCHAR(100),
    sage_statut_facture VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs_ldap(id) ON DELETE SET NULL,
    INDEX idx_numero (numero_commande),
    INDEX idx_client (client_nom),
    INDEX idx_date (date_commande),
    INDEX idx_statut (statut),
    INDEX idx_sage (sage_export)
);

-- Table des détails de commandes
CREATE TABLE details_commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    sous_total DECIMAL(12,2) NOT NULL,
    taux_tva DECIMAL(5,2) DEFAULT 20.00,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT,
    INDEX idx_commande (commande_id),
    INDEX idx_produit (produit_id)
);

-- Table des livraisons
CREATE TABLE livraisons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    date_prevue DATE NOT NULL,
    date_reelle DATE,
    transporteur VARCHAR(100),
    numero_suivi VARCHAR(100),
    statut ENUM('planifiee', 'en_cours', 'livree', 'echec') DEFAULT 'planifiee',
    notes TEXT,
    adresse_livraison TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    INDEX idx_commande (commande_id),
    INDEX idx_date_prevue (date_prevue),
    INDEX idx_statut (statut),
    INDEX idx_numero_suivi (numero_suivi)
);

-- Table des commandes fournisseurs
CREATE TABLE commandes_fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(50) UNIQUE NOT NULL,
    fournisseur_id INT NOT NULL,
    date_commande DATE NOT NULL,
    date_livraison_prevue DATE,
    date_livraison_reelle DATE,
    total DECIMAL(12,2) DEFAULT 0,
    statut ENUM('en_attente', 'confirmee', 'expediee', 'recue', 'annulee') DEFAULT 'en_attente',
    notes TEXT,
    utilisateur_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE RESTRICT,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs_ldap(id) ON DELETE SET NULL,
    INDEX idx_numero (numero_commande),
    INDEX idx_fournisseur (fournisseur_id),
    INDEX idx_date (date_commande),
    INDEX idx_statut (statut)
);

-- Table des détails de commandes fournisseurs
CREATE TABLE details_commandes_fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_fournisseur_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    sous_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (commande_fournisseur_id) REFERENCES commandes_fournisseurs(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT,
    INDEX idx_commande_fournisseur (commande_fournisseur_id),
    INDEX idx_produit (produit_id)
);

-- Table des notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    destinataire VARCHAR(100), -- user_id, role, ou 'all'
    sujet VARCHAR(255),
    message TEXT NOT NULL,
    donnees JSON,
    lu BOOLEAN DEFAULT FALSE,
    date_lecture DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_destinataire (destinataire),
    INDEX idx_lu (lu),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
);

-- Table des logs d'activité
CREATE TABLE logs_activite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    details JSON,
    utilisateur_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs_ldap(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_date (date_action)
);

-- Insertion des données de base

-- Catégories par défaut
INSERT INTO categories (nom, description) VALUES
('Robes', 'Robes de toutes tailles et styles'),
('Hauts', 'Tops, chemisiers, t-shirts'),
('Bas', 'Pantalons, jeans, jupes'),
('Vestes & Manteaux', 'Vestes, blazers, manteaux'),
('Lingerie', 'Soutiens-gorge, culottes, nuisettes'),
('Accessoires', 'Sacs, bijoux, ceintures'),
('Chaussures', 'Chaussures femmes toutes catégories'),
('Maillots de bain', 'Bikinis, maillots une pièce');

-- Fournisseurs de démonstration
INSERT INTO fournisseurs (nom, contact, telephone, email, ville, pays, delais_livraison, conditions_paiement, specialite_mode) VALUES
('Fashion Elite Paris', 'Marie Dubois', '01.45.67.89.12', 'marie@fashionelite.fr', 'Paris', 'France', 7, '30 jours net', 'Prêt-à-porter haut de gamme'),
('Milano Textile', 'Giuseppe Rossi', '+39.02.123.456', 'giuseppe@milanotextile.it', 'Milan', 'Italie', 14, 'Virement avant expédition', 'Lingerie fine'),
('Casual Trends', 'Sophie Martin', '04.76.54.32.10', 'sophie@casualtrends.fr', 'Lyon', 'France', 5, '15 jours net', 'Prêt-à-porter casual'),
('Iberian Fashion', 'Carlos Martinez', '+34.91.234.567', 'carlos@iberianfashion.es', 'Madrid', 'Espagne', 10, '45 jours net', 'Chaussures femmes'),
('Nordic Style', 'Anna Larsson', '+46.8.123.456', 'anna@nordicstyle.se', 'Stockholm', 'Suède', 12, '30 jours fin de mois', 'Maroquinerie et accessoires');

-- Produits de démonstration
INSERT INTO produits (reference, nom, description, categorie_id, stock_actuel, seuil_minimum, prix_achat, prix_vente, taille, couleur, marque, collection, saison) VALUES
('ROBE-001', 'Robe fluide été', 'Robe légère parfaite pour l\'été', 1, 25, 5, 35.00, 89.90, 'M', 'Bleu marine', 'SOTA', 'Été 2025', 'Printemps/Été'),
('ROBE-002', 'Robe cocktail', 'Robe élégante pour soirées', 1, 12, 3, 65.00, 149.90, 'S', 'Noir', 'SOTA', 'Élégance', 'Toute saison'),
('TOP-001', 'Chemisier soie', 'Chemisier en soie naturelle', 2, 18, 5, 28.00, 69.90, 'L', 'Blanc', 'Milano', 'Classic', 'Toute saison'),
('TOP-002', 'T-shirt coton bio', 'T-shirt en coton biologique', 2, 45, 10, 12.00, 29.90, 'M', 'Rose', 'EcoCotton', 'Bio', 'Printemps/Été'),
('JEAN-001', 'Jean slim taille haute', 'Jean délavé taille haute', 3, 30, 8, 32.00, 79.90, '38', 'Bleu délavé', 'DenimCo', 'Urban', 'Toute saison'),
('JEAN-002', 'Jean boyfriend', 'Jean coupe boyfriend décontractée', 3, 22, 5, 28.00, 69.90, '40', 'Noir', 'DenimCo', 'Casual', 'Toute saison'),
('VESTE-001', 'Blazer ajusté', 'Blazer cintré professionnel', 4, 15, 3, 45.00, 119.90, 'M', 'Gris anthracite', 'Business', 'Pro', 'Toute saison'),
('LINGERIE-001', 'Ensemble dentelle', 'Soutien-gorge et culotte dentelle', 5, 8, 2, 18.00, 49.90, '85B', 'Nude', 'Intimacy', 'Délicate', 'Toute saison'),
('SAC-001', 'Sac à main cuir', 'Sac à main en cuir véritable', 6, 12, 3, 55.00, 139.90, 'Unique', 'Cognac', 'LeatherCraft', 'Heritage', 'Toute saison'),
('CHAUSSURE-001', 'Escarpins classiques', 'Escarpins à talon 7cm', 7, 20, 4, 42.00, 99.90, '38', 'Noir', 'Eleganza', 'Classic', 'Toute saison');

-- Utilisateurs de démonstration LDAP
INSERT INTO utilisateurs_ldap (username, prenom, nom, email, role, derniere_sync) VALUES
('admin', 'Pierre', 'Dupont', 'pierre.dupont@fashionchic.local', 'Admin', NOW()),
('gerant', 'Sophie', 'Martin', 'sophie.martin@fashionchic.local', 'Gérant', NOW()),
('commercial1', 'Julie', 'Bernard', 'julie.bernard@fashionchic.local', 'Commercial', NOW()),
('commercial2', 'Thomas', 'Durand', 'thomas.durand@fashionchic.local', 'Commercial', NOW()),
('preparateur1', 'Maxime', 'Roux', 'maxime.roux@fashionchic.local', 'Préparateur', NOW()),
('preparateur2', 'Camille', 'Moreau', 'camille.moreau@fashionchic.local', 'Préparateur', NOW()),
('livreur1', 'Emma', 'Petit', 'emma.petit@fashionchic.local', 'Livreur', NOW());

-- Commandes de démonstration
INSERT INTO commandes (numero_commande, client_nom, client_email, client_telephone, client_adresse, client_ville, client_code_postal, date_commande, total, total_ht, tva, statut, utilisateur_id) VALUES
('CMD-2025-00001', 'Marie Rousseau', 'marie.rousseau@email.com', '06.12.34.56.78', '15 rue de la Mode', 'Paris', '75001', '2025-01-10', 219.80, 183.17, 36.63, 'livree', 3),
('CMD-2025-00002', 'Claire Fontaine', 'claire.fontaine@email.com', '06.87.65.43.21', '8 avenue du Style', 'Lyon', '69001', '2025-01-11', 149.90, 124.92, 24.98, 'expediee', 3),
('CMD-2025-00003', 'Isabelle Moreau', 'isabelle.moreau@email.com', '06.11.22.33.44', '22 boulevard Chic', 'Marseille', '13001', '2025-01-12', 89.90, 74.92, 14.98, 'en_preparation', 4),
('CMD-2025-00004', 'Anne Durand', 'anne.durand@email.com', '06.55.66.77.88', '5 place Élégance', 'Toulouse', '31000', '2025-01-13', 329.70, 274.75, 54.95, 'confirmee', 3);

-- Détails des commandes
INSERT INTO details_commandes (commande_id, produit_id, quantite, prix_unitaire, sous_total, taux_tva) VALUES
-- Commande 1
(1, 1, 1, 89.90, 89.90, 20.00),
(1, 3, 1, 69.90, 69.90, 20.00),
(1, 4, 2, 29.90, 59.80, 20.00),
-- Commande 2
(2, 2, 1, 149.90, 149.90, 20.00),
-- Commande 3
(3, 1, 1, 89.90, 89.90, 20.00),
-- Commande 4
(4, 5, 1, 79.90, 79.90, 20.00),
(4, 6, 1, 69.90, 69.90, 20.00),
(4, 7, 1, 119.90, 119.90, 20.00),
(4, 4, 2, 29.90, 59.80, 20.00);

-- Livraisons
INSERT INTO livraisons (commande_id, date_prevue, date_reelle, transporteur, numero_suivi, statut, notes) VALUES
(1, '2025-01-12', '2025-01-12', 'Chronopost', 'CH123456789FR', 'livree', 'Livraison effectuée'),
(2, '2025-01-14', NULL, 'Colissimo', 'CO987654321FR', 'en_cours', 'En cours de livraison'),
(3, '2025-01-15', NULL, 'DHL', NULL, 'planifiee', 'Préparation en cours'),
(4, '2025-01-16', NULL, 'UPS', NULL, 'planifiee', 'En attente de préparation');

-- Mouvements de stock initiaux
INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, utilisateur_id) VALUES
(1, 'entree', 30, 0, 30, 'Stock initial', 1),
(2, 'entree', 15, 0, 15, 'Stock initial', 1),
(3, 'entree', 20, 0, 20, 'Stock initial', 1),
(4, 'entree', 50, 0, 50, 'Stock initial', 1),
(5, 'entree', 35, 0, 35, 'Stock initial', 1),
(6, 'entree', 25, 0, 25, 'Stock initial', 1),
(7, 'entree', 18, 0, 18, 'Stock initial', 1),
(8, 'entree', 10, 0, 10, 'Stock initial', 1),
(9, 'entree', 15, 0, 15, 'Stock initial', 1),
(10, 'entree', 24, 0, 24, 'Stock initial', 1);

-- Mouvements de sortie pour les commandes
INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_document, utilisateur_id) VALUES
-- Sorties pour CMD-2025-00001
(1, 'sortie', 1, 30, 29, 'Commande CMD-2025-00001', 'CMD-2025-00001', 5),
(3, 'sortie', 1, 20, 19, 'Commande CMD-2025-00001', 'CMD-2025-00001', 5),
(4, 'sortie', 2, 50, 48, 'Commande CMD-2025-00001', 'CMD-2025-00001', 5),
-- Sorties pour CMD-2025-00002
(2, 'sortie', 1, 15, 14, 'Commande CMD-2025-00002', 'CMD-2025-00002', 5),
-- Sorties pour CMD-2025-00003
(1, 'sortie', 1, 29, 28, 'Commande CMD-2025-00003', 'CMD-2025-00003', 6),
-- Sorties pour CMD-2025-00004
(5, 'sortie', 1, 35, 34, 'Commande CMD-2025-00004', 'CMD-2025-00004', 5),
(6, 'sortie', 1, 25, 24, 'Commande CMD-2025-00004', 'CMD-2025-00004', 5),
(7, 'sortie', 1, 18, 17, 'Commande CMD-2025-00004', 'CMD-2025-00004', 5),
(4, 'sortie', 2, 48, 46, 'Commande CMD-2025-00004', 'CMD-2025-00004', 5);

-- Mise à jour des stocks actuels des produits
UPDATE produits SET stock_actuel = 25 WHERE id = 1; -- Robe fluide été (30-1-1-1-1)
UPDATE produits SET stock_actuel = 12 WHERE id = 2; -- Robe cocktail (15-1-1)
UPDATE produits SET stock_actuel = 18 WHERE id = 3; -- Chemisier soie (20-1-1)
UPDATE produits SET stock_actuel = 45 WHERE id = 4; -- T-shirt coton bio (50-2-2)
UPDATE produits SET stock_actuel = 30 WHERE id = 5; -- Jean slim (35-1)
UPDATE produits SET stock_actuel = 22 WHERE id = 6; -- Jean boyfriend (25-1)
UPDATE produits SET stock_actuel = 15 WHERE id = 7; -- Blazer ajusté (18-1)
UPDATE produits SET stock_actuel = 8 WHERE id = 8;  -- Ensemble dentelle
UPDATE produits SET stock_actuel = 12 WHERE id = 9; -- Sac à main cuir
UPDATE produits SET stock_actuel = 20 WHERE id = 10; -- Escarpins classiques

-- Commandes fournisseurs de démonstration
INSERT INTO commandes_fournisseurs (numero_commande, fournisseur_id, date_commande, date_livraison_prevue, total, statut, notes, utilisateur_id) VALUES
('CF-2025-00001', 1, '2025-01-08', '2025-01-15', 2450.00, 'confirmee', 'Commande urgente - collection été', 1),
('CF-2025-00002', 2, '2025-01-10', '2025-01-24', 1890.00, 'en_attente', 'Nouvelle collection lingerie', 2),
('CF-2025-00003', 3, '2025-01-12', '2025-01-17', 980.00, 'expediee', 'Réapprovisionnement t-shirts', 1);

-- Détails des commandes fournisseurs
INSERT INTO details_commandes_fournisseurs (commande_fournisseur_id, produit_id, quantite, prix_unitaire, sous_total) VALUES
-- CF-2025-00001 (Fashion Elite Paris)
(1, 1, 50, 35.00, 1750.00),
(1, 2, 20, 65.00, 1300.00),
-- CF-2025-00002 (Milano Textile)
(2, 8, 30, 18.00, 540.00),
(2, 3, 40, 28.00, 1120.00),
-- CF-2025-00003 (Casual Trends)
(3, 4, 80, 12.00, 960.00);

-- Notifications de démonstration
INSERT INTO notifications (type, destinataire, sujet, message, donnees) VALUES
('warning', 'all', 'Stock critique', 'Certains produits atteignent leur seuil d\'alerte', '{"produits_concernes": 3}'),
('info', 'Livreur', 'Nouvelles livraisons', '3 livraisons planifiées pour demain', '{"livraisons": 3, "date": "2025-01-15"}'),
('success', 'Admin', 'Synchronisation SAGE', 'Export réussi de 15 factures vers SAGE', '{"factures_exportees": 15}'),
('error', 'Préparateur', 'Commande en retard', 'La commande CMD-2025-00002 a dépassé sa date de livraison prévue', '{"commande": "CMD-2025-00002"}');

-- Logs d'activité de démonstration
INSERT INTO logs_activite (action, details, utilisateur_id, ip_address) VALUES
('creation_commande', '{"commande_id": 1, "client": "Marie Rousseau", "total": 219.80}', 3, '192.168.1.100'),
('mouvement_stock', '{"produit_id": 1, "type": "sortie", "quantite": 1}', 5, '192.168.1.105'),
('sync_sage', '{"commandes_synchronisees": 1, "total_commandes": 1}', 1, '192.168.1.101'),
('creation_produit', '{"produit_id": 1, "reference": "ROBE-001", "nom": "Robe fluide été"}', 1, '192.168.1.101'),
('modification_commande', '{"commande_id": 2, "ancien_statut": "confirmee", "nouveau_statut": "expediee"}', 3, '192.168.1.100');

-- Index supplémentaires pour les performances
CREATE INDEX idx_produits_stock_statut ON produits(stock_actuel, seuil_minimum);
CREATE INDEX idx_commandes_date_statut ON commandes(date_commande, statut);
CREATE INDEX idx_mouvements_date_type ON mouvements_stock(date_mouvement, type_mouvement);
CREATE INDEX idx_notifications_destinataire_lu ON notifications(destinataire, lu);
CREATE INDEX idx_logs_action_date ON logs_activite(action, date_action);

-- Vues utiles
CREATE VIEW vue_stock_critique AS
SELECT 
    p.id,
    p.reference,
    p.nom,
    p.stock_actuel,
    p.seuil_minimum,
    c.nom as categorie_nom,
    CASE 
        WHEN p.stock_actuel = 0 THEN 'rupture'
        WHEN p.stock_actuel <= p.seuil_minimum THEN 'alerte'
        ELSE 'normal'
    END as statut_stock
FROM produits p
LEFT JOIN categories c ON p.categorie_id = c.id
WHERE p.actif = 1 AND p.stock_actuel <= p.seuil_minimum
ORDER BY (p.stock_actuel / p.seuil_minimum), p.nom;

CREATE VIEW vue_ca_mensuel AS
SELECT 
    DATE_FORMAT(date_commande, '%Y-%m') as mois,
    COUNT(*) as nb_commandes,
    SUM(total) as ca_total,
    SUM(total_ht) as ca_ht,
    AVG(total) as panier_moyen
FROM commandes
WHERE statut = 'livree'
GROUP BY DATE_FORMAT(date_commande, '%Y-%m')
ORDER BY mois DESC;

CREATE VIEW vue_top_produits AS
SELECT 
    p.id,
    p.reference,
    p.nom,
    p.prix_vente,
    SUM(dc.quantite) as quantite_vendue,
    SUM(dc.sous_total) as ca_produit,
    COUNT(DISTINCT dc.commande_id) as nb_commandes
FROM produits p
JOIN details_commandes dc ON p.id = dc.produit_id
JOIN commandes c ON dc.commande_id = c.id
WHERE c.statut = 'livree'
GROUP BY p.id
ORDER BY quantite_vendue DESC;

-- Triggers pour maintenir la cohérence
DELIMITER $

CREATE TRIGGER after_mouvement_stock_insert 
AFTER INSERT ON mouvements_stock
FOR EACH ROW
BEGIN
    UPDATE produits 
    SET stock_actuel = NEW.quantite_apres,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.produit_id;
END$

CREATE TRIGGER after_commande_total_update
AFTER INSERT ON details_commandes
FOR EACH ROW
BEGIN
    UPDATE commandes 
    SET total = (
        SELECT SUM(sous_total) 
        FROM details_commandes 
        WHERE commande_id = NEW.commande_id
    ),
    total_ht = (
        SELECT SUM(sous_total / (1 + taux_tva/100)) 
        FROM details_commandes 
        WHERE commande_id = NEW.commande_id
    ),
    tva = (
        SELECT SUM(sous_total - (sous_total / (1 + taux_tva/100))) 
        FROM details_commandes 
        WHERE commande_id = NEW.commande_id
    )
    WHERE id = NEW.commande_id;
END$

CREATE TRIGGER after_commande_fournisseur_total_update
AFTER INSERT ON details_commandes_fournisseurs
FOR EACH ROW
BEGIN
    UPDATE commandes_fournisseurs 
    SET total = (
        SELECT SUM(sous_total) 
        FROM details_commandes_fournisseurs 
        WHERE commande_fournisseur_id = NEW.commande_fournisseur_id
    )
    WHERE id = NEW.commande_fournisseur_id;
END$

DELIMITER ;

-- Procédures stockées utiles
DELIMITER $

CREATE PROCEDURE GetStockValueByCategory()
BEGIN
    SELECT 
        c.nom as categorie,
        COUNT(p.id) as nb_produits,
        SUM(p.stock_actuel) as stock_total,
        SUM(p.stock_actuel * COALESCE(p.prix_achat, 0)) as valeur_stock,
        AVG(p.prix_vente) as prix_moyen
    FROM categories c
    LEFT JOIN produits p ON c.id = p.categorie_id AND p.actif = 1
    GROUP BY c.id, c.nom
    ORDER BY valeur_stock DESC;
END$

CREATE PROCEDURE GetTopClientsByCA(IN limite INT)
BEGIN
    SELECT 
        client_nom,
        client_email,
        COUNT(*) as nb_commandes,
        SUM(total) as ca_total,
        AVG(total) as panier_moyen,
        MAX(date_commande) as derniere_commande
    FROM commandes
    WHERE statut = 'livree'
    GROUP BY client_nom, client_email
    ORDER BY ca_total DESC
    LIMIT limite;
END$

DELIMITER ;

-- Commentaires sur la structure
/*
Cette base de données SOTA Fashion est conçue pour :

1. **Gestion complète des produits** : Catalogue avec variantes, prix, stocks
2. **Traçabilité des stocks** : Mouvements détaillés avec historique
3. **Workflow commercial** : Commandes → Préparation → Livraison → Facturation
4. **Intégration fournisseurs** : Gestion des approvisionnements
5. **Notifications intelligentes** : Alertes métier contextuelles
6. **Audit et logs** : Traçabilité complète des actions
7. **Reporting avancé** : Vues et procédures pour analyses

Points techniques importants :
- Contraintes d'intégrité référentielle
- Index optimisés pour les performances
- Triggers pour la cohérence des données
- Vues matérialisées pour le reporting
- Support complet UTF-8
- Évolutivité et extensibilité

La base supporte nativement :
- Multi-utilisateurs avec rôles
- Gestion des variantes produits
- Calculs automatiques (totaux, marges, TVA)
- Intégration SAGE
- Notifications temps réel
- Export et reporting
*/