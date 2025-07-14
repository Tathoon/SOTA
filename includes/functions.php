<?php
require_once __DIR__ . '/../config/database.php';

class SotaManager {
    public $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // ==========================================
    // DASHBOARD ET STATISTIQUES
    // ==========================================
    public function getStatistiquesDashboard($role = null) {
        $stats = [];
        try {
            // Total produits actifs
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM produits WHERE actif = 1");
            $stmt->execute();
            $stats['total_produits'] = $stmt->fetch()['total'];

            // Produits en rupture
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM produits WHERE stock_actuel = 0 AND actif = 1");
            $stmt->execute();
            $stats['produits_rupture'] = $stmt->fetch()['total'];

            // Produits en alerte
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM produits WHERE stock_actuel <= seuil_minimum AND stock_actuel > 0 AND actif = 1");
            $stmt->execute();
            $stats['produits_alerte'] = $stmt->fetch()['total'];

            // Commandes en attente
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM commandes WHERE statut IN ('en_attente', 'confirmee')");
            $stmt->execute();
            $stats['commandes_attente'] = $stmt->fetch()['total'];

            // Livraisons en cours
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM livraisons WHERE statut IN ('planifiee', 'en_cours')");
            $stmt->execute();
            $stats['livraisons_cours'] = $stmt->fetch()['total'];

            // CA du mois
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(total), 0) as ca FROM commandes WHERE MONTH(date_commande) = MONTH(CURRENT_DATE) AND YEAR(date_commande) = YEAR(CURRENT_DATE) AND statut = 'livree'");
            $stmt->execute();
            $stats['ca_mois'] = $stmt->fetch()['ca'];

            // Commandes du jour
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM commandes WHERE DATE(date_commande) = CURRENT_DATE");
            $stmt->execute();
            $stats['commandes_jour'] = $stmt->fetch()['total'];

            // Fournisseurs actifs
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM fournisseurs WHERE actif = 1");
            $stmt->execute();
            $stats['fournisseurs_actifs'] = $stmt->fetch()['total'];

            // Utilisateurs actifs (pour Admin)
            if ($role === 'Admin') {
                $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM utilisateurs_ldap WHERE actif = 1");
                $stmt->execute();
                $stats['users_actifs'] = $stmt->fetch()['total'];
            }

        } catch (Exception $e) {
            error_log("Erreur statistiques: " . $e->getMessage());
            $stats = [
                'total_produits' => 0,
                'produits_rupture' => 0,
                'produits_alerte' => 0,
                'commandes_attente' => 0,
                'livraisons_cours' => 0,
                'ca_mois' => 0,
                'commandes_jour' => 0,
                'fournisseurs_actifs' => 0,
                'users_actifs' => 0
            ];
        }
        return $stats;
    }

    public function getStatistiquesDetaillees() {
        try {
            $stats = [];
            
            // Top catégories
            $stmt = $this->db->prepare("
                SELECT c.nom, COUNT(p.id) as nb_produits 
                FROM categories c 
                LEFT JOIN produits p ON c.id = p.categorie_id AND p.actif = 1
                GROUP BY c.id, c.nom 
                ORDER BY nb_produits DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $stats['top_categories'] = $stmt->fetchAll();

            // CA mois précédent
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(total), 0) as ca 
                FROM commandes 
                WHERE MONTH(date_commande) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH) 
                AND YEAR(date_commande) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
                AND statut = 'livree'
            ");
            $stmt->execute();
            $stats['ca_mois_precedent'] = $stmt->fetch()['ca'];

            // Calcul évolution
            $ca_actuel = $this->getStatistiquesDashboard()['ca_mois'];
            $ca_precedent = $stats['ca_mois_precedent'];
            
            if ($ca_precedent > 0) {
                $stats['evolution_ca'] = (($ca_actuel - $ca_precedent) / $ca_precedent) * 100;
            } else {
                $stats['evolution_ca'] = $ca_actuel > 0 ? 100 : 0;
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Erreur statistiques détaillées: " . $e->getMessage());
            return [];
        }
    }

    public function getProduitsAlerteSeuil() {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.nom as categorie_nom 
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                WHERE p.stock_actuel <= p.seuil_minimum AND p.actif = 1
                ORDER BY (p.stock_actuel / GREATEST(p.seuil_minimum, 1)) ASC
                LIMIT 20
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur alertes stock: " . $e->getMessage());
            return [];
        }
    }

    // ==========================================
    // GESTION DES PRODUITS
    // ==========================================
    public function getProduits($search = '', $category = '', $statut_stock = '', $limit = null) {
        try {
            $sql = "SELECT p.*, c.nom as categorie_nom, 
                           CASE 
                               WHEN p.stock_actuel = 0 THEN 'rupture'
                               WHEN p.stock_actuel <= p.seuil_minimum THEN 'alerte'
                               ELSE 'normal'
                           END as statut_stock,
                           (p.prix_vente - COALESCE(p.prix_achat, 0)) as marge_unitaire
                    FROM produits p 
                    LEFT JOIN categories c ON p.categorie_id = c.id 
                    WHERE p.actif = 1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (p.nom LIKE ? OR p.reference LIKE ? OR p.description LIKE ? OR p.marque LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (!empty($category)) {
                $sql .= " AND p.categorie_id = ?";
                $params[] = $category;
            }

            if (!empty($statut_stock)) {
                switch ($statut_stock) {
                    case 'rupture':
                        $sql .= " AND p.stock_actuel = 0";
                        break;
                    case 'alerte':
                        $sql .= " AND p.stock_actuel > 0 AND p.stock_actuel <= p.seuil_minimum";
                        break;
                    case 'normal':
                        $sql .= " AND p.stock_actuel > p.seuil_minimum";
                        break;
                }
            }

            $sql .= " ORDER BY p.nom";

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getProduits: " . $e->getMessage());
            return [];
        }
    }

    public function ajouterProduit($data) {
        try {
            $this->db->beginTransaction();

            // Validation des données
            if (empty($data['reference']) || empty($data['nom']) || empty($data['prix_vente'])) {
                throw new Exception("Référence, nom et prix de vente sont obligatoires");
            }

            // Vérifier unicité de la référence
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM produits WHERE reference = ?");
            $stmt->execute([$data['reference']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette référence existe déjà");
            }

            $stmt = $this->db->prepare("
                INSERT INTO produits (
                    reference, nom, description, categorie_id, stock_actuel, 
                    seuil_minimum, prix_achat, prix_vente, taille, couleur, 
                    emplacement, lot_minimum, poids, composition, saison, marque, collection
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['reference'],
                $data['nom'],
                $data['description'] ?? null,
                $data['categorie_id'] ?? null,
                $data['stock_actuel'] ?? 0,
                $data['seuil_minimum'] ?? 5,
                $data['prix_achat'] ?? null,
                $data['prix_vente'],
                $data['taille'] ?? null,
                $data['couleur'] ?? null,
                $data['emplacement'] ?? null,
                $data['lot_minimum'] ?? 1,
                $data['poids'] ?? null,
                $data['composition'] ?? null,
                $data['saison'] ?? null,
                $data['marque'] ?? null,
                $data['collection'] ?? null
            ]);

            $produit_id = $this->db->lastInsertId();

            // Mouvement de stock initial si quantité > 0
            if (($data['stock_actuel'] ?? 0) > 0) {
                $this->ajouterMouvementStock(
                    $produit_id,
                    'entree',
                    $data['stock_actuel'],
                    'Stock initial',
                    $data['utilisateur_id'] ?? null
                );
            }

            $this->db->commit();
            return $produit_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getProduitById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.nom as categorie_nom,
                       CASE 
                           WHEN p.stock_actuel = 0 THEN 'rupture'
                           WHEN p.stock_actuel <= p.seuil_minimum THEN 'alerte'
                           ELSE 'normal'
                       END as statut_stock
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                WHERE p.id = ? AND p.actif = 1
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erreur getProduitById: " . $e->getMessage());
            return null;
        }
    }

    public function modifierProduit($id, $data) {
        try {
            $this->db->beginTransaction();

            // Vérifier que le produit existe
            $produit_actuel = $this->getProduitById($id);
            if (!$produit_actuel) {
                throw new Exception("Produit non trouvé");
            }

            // Vérifier unicité de la référence (sauf pour le produit actuel)
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM produits WHERE reference = ? AND id != ?");
            $stmt->execute([$data['reference'], $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette référence existe déjà");
            }

            $stmt = $this->db->prepare("
                UPDATE produits SET 
                    reference = ?, nom = ?, description = ?, categorie_id = ?, 
                    seuil_minimum = ?, prix_achat = ?, prix_vente = ?, taille = ?, 
                    couleur = ?, emplacement = ?, lot_minimum = ?, poids = ?, 
                    composition = ?, saison = ?, marque = ?, collection = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['reference'],
                $data['nom'],
                $data['description'] ?? null,
                $data['categorie_id'] ?? null,
                $data['seuil_minimum'] ?? 5,
                $data['prix_achat'] ?? null,
                $data['prix_vente'],
                $data['taille'] ?? null,
                $data['couleur'] ?? null,
                $data['emplacement'] ?? null,
                $data['lot_minimum'] ?? 1,
                $data['poids'] ?? null,
                $data['composition'] ?? null,
                $data['saison'] ?? null,
                $data['marque'] ?? null,
                $data['collection'] ?? null,
                $id
            ]);

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ==========================================
    // GESTION DES CATÉGORIES
    // ==========================================
    public function getCategories($actif_only = true) {
        try {
            $sql = "SELECT * FROM categories";
            if ($actif_only) {
                $sql .= " WHERE actif = 1";
            }
            $sql .= " ORDER BY nom";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getCategories: " . $e->getMessage());
            return [];
        }
    }

    public function ajouterCategorie($nom, $description = '') {
        try {
            // Vérifier unicité
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM categories WHERE nom = ?");
            $stmt->execute([$nom]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette catégorie existe déjà");
            }

            $stmt = $this->db->prepare("INSERT INTO categories (nom, description) VALUES (?, ?)");
            return $stmt->execute([$nom, $description]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getCategorieById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erreur getCategorieById: " . $e->getMessage());
            return null;
        }
    }

    // ==========================================
    // GESTION DES STOCKS ET MOUVEMENTS
    // ==========================================
    public function ajouterMouvementStock($produit_id, $type_mouvement, $quantite, $motif = '', $utilisateur_id = null, $reference_document = null) {
        try {
            $this->db->beginTransaction();

            // Récupérer le stock actuel
            $stmt = $this->db->prepare("SELECT stock_actuel FROM produits WHERE id = ? AND actif = 1");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();

            if (!$produit) {
                throw new Exception("Produit non trouvé");
            }

            $stock_avant = $produit['stock_actuel'];
            $quantite_mouvement = $quantite;

            // Calculer le nouveau stock
            switch ($type_mouvement) {
                case 'entree':
                    $stock_apres = $stock_avant + $quantite;
                    break;
                case 'sortie':
                    if ($stock_avant < $quantite) {
                        throw new Exception("Stock insuffisant (disponible: $stock_avant)");
                    }
                    $stock_apres = $stock_avant - $quantite;
                    $quantite_mouvement = -$quantite;
                    break;
                case 'ajustement':
                    $stock_apres = $quantite;
                    $quantite_mouvement = $stock_apres - $stock_avant;
                    break;
                default:
                    throw new Exception("Type de mouvement invalide");
            }

            // Mettre à jour le stock
            $stmt = $this->db->prepare("UPDATE produits SET stock_actuel = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$stock_apres, $produit_id]);

            // Enregistrer le mouvement
            $stmt = $this->db->prepare("
                INSERT INTO mouvements_stock 
                (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, reference_document, utilisateur_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $produit_id, $type_mouvement, abs($quantite_mouvement), 
                $stock_avant, $stock_apres, $motif, $reference_document, $utilisateur_id
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getHistoriqueMouvements($produit_id = null, $limit = 50) {
        try {
            $sql = "
                SELECT ms.*, p.nom as produit_nom, p.reference as produit_reference,
                       u.prenom, u.nom as user_nom
                FROM mouvements_stock ms
                LEFT JOIN produits p ON ms.produit_id = p.id
                LEFT JOIN utilisateurs_ldap u ON ms.utilisateur_id = u.id
            ";
            $params = [];

            if ($produit_id) {
                $sql .= " WHERE ms.produit_id = ?";
                $params[] = $produit_id;
            }

            $sql .= " ORDER BY ms.date_mouvement DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getHistoriqueMouvements: " . $e->getMessage());
            return [];
        }
    }

    // ==========================================
    // GESTION DES FOURNISSEURS
    // ==========================================
    public function getFournisseurs($search = '') {
        try {
            $sql = "SELECT * FROM fournisseurs WHERE actif = 1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (nom LIKE ? OR contact LIKE ? OR ville LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY nom";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getFournisseurs: " . $e->getMessage());
            return [];
        }
    }

    public function ajouterFournisseur($data) {
        try {
            // Validation des données
            if (empty($data['nom'])) {
                throw new Exception("Le nom du fournisseur est obligatoire");
            }

            // Vérifier unicité du SIRET si fourni
            if (!empty($data['siret'])) {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM fournisseurs WHERE siret = ?");
                $stmt->execute([$data['siret']]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Ce SIRET existe déjà");
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO fournisseurs (
                    nom, siret, contact, telephone, email, adresse, ville, 
                    code_postal, pays, delais_livraison, conditions_paiement, specialite_mode
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['nom'],
                $data['siret'] ?? null,
                $data['contact'] ?? null,
                $data['telephone'] ?? null,
                $data['email'] ?? null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['code_postal'] ?? null,
                $data['pays'] ?? 'France',
                $data['delais_livraison'] ?? 7,
                $data['conditions_paiement'] ?? null,
                $data['specialite_mode'] ?? null
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getFournisseurById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM fournisseurs WHERE id = ? AND actif = 1");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Erreur getFournisseurById: " . $e->getMessage());
            return null;
        }
    }

    // ==========================================
    // GESTION DES COMMANDES
    // ==========================================
    public function getCommandes($statut = '', $limit = null, $search = '') {
        try {
            $sql = "
                SELECT c.*, 
                       COALESCE(COUNT(dc.id), 0) as nb_produits,
                       COALESCE(SUM(dc.quantite), 0) as quantite_totale
                FROM commandes c
                LEFT JOIN details_commandes dc ON c.id = dc.commande_id
            ";
            $params = [];
            $conditions = [];

            if (!empty($statut)) {
                $conditions[] = "c.statut = ?";
                $params[] = $statut;
            }

            if (!empty($search)) {
                $conditions[] = "(c.numero_commande LIKE ? OR c.client_nom LIKE ? OR c.client_email LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getCommandes: " . $e->getMessage());
            return [];
        }
    }

    public function creerCommande($data) {
        try {
            $this->db->beginTransaction();

            // Validation des données
            $erreurs = $this->validerDonneesCommande($data);
            if (!empty($erreurs)) {
                throw new Exception(implode(', ', $erreurs));
            }

            // Générer numéro de commande
            $numero = $this->genererNumeroCommande();

            // Créer la commande
            $stmt = $this->db->prepare("
                INSERT INTO commandes (
                    numero_commande, client_nom, client_email, client_telephone, 
                    client_adresse, client_code_postal, client_ville, date_commande, 
                    date_livraison_prevue, utilisateur_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $numero,
                $data['client_nom'],
                $data['client_email'] ?? null,
                $data['client_telephone'] ?? null,
                $data['client_adresse'] ?? null,
                $data['client_code_postal'] ?? null,
                $data['client_ville'] ?? null,
                $data['date_commande'] ?? date('Y-m-d'),
                $data['date_livraison_prevue'] ?? null,
                $data['utilisateur_id']
            ]);

            $commande_id = $this->db->lastInsertId();

            // Ajouter les produits
            if (!empty($data['produits'])) {
                $total = 0;
                $total_ht = 0;
                $tva_totale = 0;

                foreach ($data['produits'] as $produit) {
                    // Vérifier stock disponible
                    $stmt = $this->db->prepare("SELECT stock_actuel, nom FROM produits WHERE id = ? AND actif = 1");
                    $stmt->execute([$produit['produit_id']]);
                    $prod_info = $stmt->fetch();
                    
                    if (!$prod_info) {
                        throw new Exception("Produit ID {$produit['produit_id']} non trouvé");
                    }
                    
                    if ($prod_info['stock_actuel'] < $produit['quantite']) {
                        throw new Exception("Stock insuffisant pour {$prod_info['nom']} (disponible: {$prod_info['stock_actuel']})");
                    }

                    $sous_total = $produit['quantite'] * $produit['prix_unitaire'];
                    $taux_tva = $produit['taux_tva'] ?? 20.00;
                    $montant_ht = $sous_total / (1 + $taux_tva / 100);
                    $montant_tva = $sous_total - $montant_ht;

                    $total += $sous_total;
                    $total_ht += $montant_ht;
                    $tva_totale += $montant_tva;

                    $stmt = $this->db->prepare("
                        INSERT INTO details_commandes 
                        (commande_id, produit_id, quantite, prix_unitaire, sous_total, taux_tva) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $commande_id, $produit['produit_id'], 
                        $produit['quantite'], $produit['prix_unitaire'], 
                        $sous_total, $taux_tva
                    ]);
                }

                // Mettre à jour le total de la commande
                $stmt = $this->db->prepare("UPDATE commandes SET total = ?, total_ht = ?, tva = ? WHERE id = ?");
                $stmt->execute([$total, $total_ht, $tva_totale, $commande_id]);
            }

            $this->db->commit();
            return $commande_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function genererNumeroCommande() {
        do {
            $numero = 'CMD-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM commandes WHERE numero_commande = ?");
            $stmt->execute([$numero]);
        } while ($stmt->fetchColumn() > 0);
        
        return $numero;
    }

    private function validerDonneesCommande($data) {
        $erreurs = [];

        if (empty($data['client_nom'])) {
            $erreurs[] = "Le nom du client est obligatoire";
        }

        if (!empty($data['client_email']) && !filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = "L'email du client n'est pas valide";
        }

        if (empty($data['produits']) || !is_array($data['produits'])) {
            $erreurs[] = "Au moins un produit doit être commandé";
        } else {
            foreach ($data['produits'] as $index => $produit) {
                if (empty($produit['produit_id']) || empty($produit['quantite']) || empty($produit['prix_unitaire'])) {
                    $erreurs[] = "Produit #" . ($index + 1) . ": sélection, quantité et prix obligatoires";
                }
                if (!is_numeric($produit['quantite']) || $produit['quantite'] <= 0) {
                    $erreurs[] = "Produit #" . ($index + 1) . ": quantité invalide";
                }
            }
        }

        return $erreurs;
    }

    public function mettreAJourStatutCommande($commande_id, $nouveau_statut) {
        try {
            $this->db->beginTransaction();

            // Récupérer l'ancien statut
            $stmt = $this->db->prepare("SELECT statut FROM commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();
            
            if (!$commande) {
                throw new Exception("Commande non trouvée");
            }

            $ancien_statut = $commande['statut'];

            // Mettre à jour le statut
            $stmt = $this->db->prepare("UPDATE commandes SET statut = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$nouveau_statut, $commande_id]);

            // Gestion automatique du stock selon le changement de statut
            if ($ancien_statut !== $nouveau_statut) {
                $this->gererStockSelonStatut($commande_id, $ancien_statut, $nouveau_statut);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function gererStockSelonStatut($commande_id, $ancien_statut, $nouveau_statut) {
        // Récupérer les détails de la commande
        $stmt = $this->db->prepare("
            SELECT dc.*, c.numero_commande
            FROM details_commandes dc
            JOIN commandes c ON dc.commande_id = c.id
            WHERE dc.commande_id = ?
        ");
        $stmt->execute([$commande_id]);
        $details = $stmt->fetchAll();

        foreach ($details as $detail) {
            $motif = "Commande {$detail['numero_commande']} - {$ancien_statut} → {$nouveau_statut}";

            // Réserver le stock lors de la confirmation
            if ($ancien_statut === 'en_attente' && $nouveau_statut === 'confirmee') {
                $this->ajouterMouvementStock(
                    $detail['produit_id'],
                    'sortie',
                    $detail['quantite'],
                    $motif,
                    null,
                    $detail['numero_commande']
                );
            }
            // Remettre en stock si annulation
            elseif (in_array($ancien_statut, ['confirmee', 'en_preparation']) && $nouveau_statut === 'annulee') {
                $this->ajouterMouvementStock(
                    $detail['produit_id'],
                    'entree',
                    $detail['quantite'],
                    $motif,
                    null,
                    $detail['numero_commande']
                );
            }
        }
    }

    public function getDetailsCommande($commande_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();
            
            if (!$commande) return null;

            $stmt = $this->db->prepare("
                SELECT dc.*, p.nom as produit_nom, p.reference, p.taille, p.couleur
                FROM details_commandes dc
                LEFT JOIN produits p ON dc.produit_id = p.id
                WHERE dc.commande_id = ?
            ");
            $stmt->execute([$commande_id]);
            $commande['produits'] = $stmt->fetchAll();

            return $commande;
        } catch (Exception $e) {
            error_log("Erreur getDetailsCommande: " . $e->getMessage());
            return null;
        }
    }

    // ==========================================
    // GESTION DES LIVRAISONS
    // ==========================================
    public function getLivraisons($statut = '', $limit = 50) {
        try {
            $sql = "
                SELECT l.*, c.numero_commande, c.client_nom, c.total, c.client_adresse
                FROM livraisons l
                JOIN commandes c ON l.commande_id = c.id
            ";
            $params = [];

            if (!empty($statut)) {
                $sql .= " WHERE l.statut = ?";
                $params[] = $statut;
            }

            $sql .= " ORDER BY l.date_prevue ASC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getLivraisons: " . $e->getMessage());
            return [];
        }
    }

    public function creerLivraison($commande_id, $data) {
        try {
            $this->db->beginTransaction();

            // Vérifier que la commande existe et peut être livrée
            $stmt = $this->db->prepare("SELECT statut FROM commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();
            
            if (!$commande) {
                throw new Exception("Commande non trouvée");
            }

            if (!in_array($commande['statut'], ['confirmee', 'en_preparation'])) {
                throw new Exception("Cette commande ne peut pas être livrée dans son état actuel");
            }

            $stmt = $this->db->prepare("
                INSERT INTO livraisons (commande_id, date_prevue, transporteur, notes, adresse_livraison)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $commande_id, 
                $data['date_prevue'], 
                $data['transporteur'] ?? null, 
                $data['notes'] ?? null,
                $data['adresse_livraison'] ?? null
            ]);
            
            if ($result) {
                // Mettre à jour le statut de la commande
                $this->mettreAJourStatutCommande($commande_id, 'en_preparation');
            }
            
            $this->db->commit();
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function mettreAJourLivraison($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE livraisons SET 
                    date_prevue = ?, date_reelle = ?, transporteur = ?, 
                    numero_suivi = ?, statut = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['date_prevue'],
                $data['date_reelle'] ?? null,
                $data['transporteur'] ?? null,
                $data['numero_suivi'] ?? null,
                $data['statut'],
                $data['notes'] ?? null,
                $id
            ]);

            // Si livraison terminée, mettre à jour la commande
            if ($data['statut'] === 'livree') {
                $stmt = $this->db->prepare("SELECT commande_id FROM livraisons WHERE id = ?");
                $stmt->execute([$id]);
                $livraison = $stmt->fetch();
                
                if ($livraison) {
                    $this->mettreAJourStatutCommande($livraison['commande_id'], 'livree');
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("Erreur mettreAJourLivraison: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // GESTION DES UTILISATEURS LDAP
    // ==========================================
    public function syncUtilisateurLDAP($ldap_data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO utilisateurs_ldap (username, prenom, nom, email, role, derniere_sync)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE 
                    prenom = VALUES(prenom),
                    nom = VALUES(nom), 
                    email = VALUES(email),
                    role = VALUES(role),
                    derniere_sync = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                $ldap_data['username'],
                $ldap_data['prenom'],
                $ldap_data['nom'],
                $ldap_data['email'],
                $ldap_data['role']
            ]);
        } catch (Exception $e) {
            error_log("Erreur syncUtilisateurLDAP: " . $e->getMessage());
            return false;
        }
    }

    public function getUtilisateursLDAP() {
        try {
            $stmt = $this->db->prepare("
                SELECT *, 
                       CASE 
                           WHEN derniere_connexion IS NULL THEN 'Jamais connecté'
                           WHEN derniere_connexion < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Inactif'
                           ELSE 'Actif'
                       END as statut_connexion
                FROM utilisateurs_ldap 
                WHERE actif = 1
                ORDER BY derniere_sync DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getUtilisateursLDAP: " . $e->getMessage());
            return [];
        }
    }

    // ==========================================
    // COMMANDES FOURNISSEURS
    // ==========================================
    public function getCommandesFournisseurs($fournisseur_id = null, $limit = 50) {
        try {
            $sql = "
                SELECT cf.*, f.nom as fournisseur_nom,
                       COUNT(dcf.id) as nb_produits,
                       SUM(dcf.quantite) as quantite_totale
                FROM commandes_fournisseurs cf
                LEFT JOIN fournisseurs f ON cf.fournisseur_id = f.id
                LEFT JOIN details_commandes_fournisseurs dcf ON cf.id = dcf.commande_fournisseur_id
            ";
            $params = [];

            if ($fournisseur_id) {
                $sql .= " WHERE cf.fournisseur_id = ?";
                $params[] = $fournisseur_id;
            }

            $sql .= " GROUP BY cf.id ORDER BY cf.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Erreur getCommandesFournisseurs: " . $e->getMessage());
            return [];
        }
    }

    public function creerCommandeFournisseur($data) {
        try {
            $this->db->beginTransaction();

            // Générer numéro de commande fournisseur
            $numero = 'CF-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Vérifier unicité
            while ($this->numeroCommandeFournisseurExiste($numero)) {
                $numero = 'CF-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }

            // Créer la commande fournisseur
            $stmt = $this->db->prepare("
                INSERT INTO commandes_fournisseurs (
                    numero_commande, fournisseur_id, date_commande, 
                    date_livraison_prevue, notes, utilisateur_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $numero,
                $data['fournisseur_id'],
                $data['date_commande'] ?? date('Y-m-d'),
                $data['date_livraison_prevue'] ?? null,
                $data['notes'] ?? null,
                $data['utilisateur_id']
            ]);

            $commande_id = $this->db->lastInsertId();

            // Ajouter les produits
            if (!empty($data['produits'])) {
                $total = 0;
                foreach ($data['produits'] as $produit) {
                    $sous_total = $produit['quantite'] * $produit['prix_unitaire'];
                    $total += $sous_total;

                    $stmt = $this->db->prepare("
                        INSERT INTO details_commandes_fournisseurs 
                        (commande_fournisseur_id, produit_id, quantite, prix_unitaire, sous_total) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $commande_id, $produit['produit_id'], 
                        $produit['quantite'], $produit['prix_unitaire'], $sous_total
                    ]);
                }

                // Mettre à jour le total
                $stmt = $this->db->prepare("UPDATE commandes_fournisseurs SET total = ? WHERE id = ?");
                $stmt->execute([$total, $commande_id]);
            }

            $this->db->commit();
            return $commande_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function numeroCommandeFournisseurExiste($numero) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM commandes_fournisseurs WHERE numero_commande = ?");
        $stmt->execute([$numero]);
        return $stmt->fetchColumn() > 0;
    }

    // ==========================================
    // NOTIFICATIONS ET ALERTES
    // ==========================================
    public function verifierAlertes() {
        $alertes = [];

        try {
            // Alertes stock critique
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as nb FROM produits 
                WHERE stock_actuel <= seuil_minimum AND actif = 1
            ");
            $stmt->execute();
            $stock_critique = $stmt->fetch()['nb'];

            if ($stock_critique > 0) {
                $alertes[] = [
                    'type' => 'stock_critique',
                    'niveau' => 'warning',
                    'message' => "$stock_critique produit(s) en stock critique",
                    'donnees' => ['count' => $stock_critique],
                    'timestamp' => time()
                ];
            }

            // Commandes en retard
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as nb FROM commandes 
                WHERE date_livraison_prevue < CURRENT_DATE 
                AND statut NOT IN ('livree', 'annulee')
            ");
            $stmt->execute();
            $commandes_retard = $stmt->fetch()['nb'];

            if ($commandes_retard > 0) {
                $alertes[] = [
                    'type' => 'livraison_retard',
                    'niveau' => 'error',
                    'message' => "$commandes_retard commande(s) en retard de livraison",
                    'donnees' => ['count' => $commandes_retard],
                    'timestamp' => time()
                ];
            }

            // Nouvelles commandes du jour
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as nb FROM commandes 
                WHERE DATE(created_at) = CURRENT_DATE
            ");
            $stmt->execute();
            $nouvelles_commandes = $stmt->fetch()['nb'];

            if ($nouvelles_commandes > 0) {
                $alertes[] = [
                    'type' => 'nouvelles_commandes',
                    'niveau' => 'info',
                    'message' => "$nouvelles_commandes nouvelle(s) commande(s) aujourd'hui",
                    'donnees' => ['count' => $nouvelles_commandes],
                    'timestamp' => time()
                ];
            }

        } catch (Exception $e) {
            error_log("Erreur verifierAlertes: " . $e->getMessage());
        }

        return $alertes;
    }

    public function ajouterNotification($type, $destinataire, $sujet, $message, $donnees = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (type, destinataire, sujet, message, donnees)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $type, $destinataire, $sujet, $message, 
                $donnees ? json_encode($donnees) : null
            ]);
        } catch (Exception $e) {
            error_log("Erreur ajouterNotification: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // EXPORTS ET RAPPORTS
    // ==========================================
    public function exporterProduits($format = 'csv') {
        try {
            $produits = $this->getProduits();
            
            if ($format === 'csv') {
                return $this->exporterCSV($produits, 'produits', [
                    'reference' => 'Référence',
                    'nom' => 'Nom',
                    'categorie_nom' => 'Catégorie',
                    'stock_actuel' => 'Stock',
                    'seuil_minimum' => 'Seuil',
                    'prix_vente' => 'Prix',
                    'taille' => 'Taille',
                    'couleur' => 'Couleur',
                    'marque' => 'Marque'
                ]);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Erreur exporterProduits: " . $e->getMessage());
            return false;
        }
    }

    private function exporterCSV($data, $filename, $headers) {
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, array_values($headers));
        
        // Data
        foreach ($data as $row) {
            $csvRow = [];
            foreach (array_keys($headers) as $key) {
                $csvRow[] = $row[$key] ?? '';
            }
            fputcsv($csvRow);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        // Headers pour téléchargement
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($csvContent));
        
        echo $csvContent;
        exit();
    }
}

// ==========================================
// FONCTIONS UTILITAIRES
// ==========================================
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' €';
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

function getStatusBadge($status) {
    $badges = [
        // Statuts produits
        'normal' => '<span class="status actif">Normal</span>',
        'alerte' => '<span class="status alerte">Alerte</span>',
        'rupture' => '<span class="status rupture">Rupture</span>',
        
        // Statuts commandes
        'en_attente' => '<span class="status en-attente">En attente</span>',
        'confirmee' => '<span class="status confirmee">Confirmée</span>',
        'en_preparation' => '<span class="status en-preparation">En préparation</span>',
        'expediee' => '<span class="status expediee">Expédiée</span>',
        'livree' => '<span class="status livree">Livrée</span>',
        'annulee' => '<span class="status annulee">Annulée</span>',
        
        // Statuts livraisons
        'planifiee' => '<span class="status en-attente">Planifiée</span>',
        'en_cours' => '<span class="status en-preparation">En cours</span>',
        'echec' => '<span class="status rupture">Échec</span>',
        
        // Statuts fournisseurs
        'recue' => '<span class="status livree">Reçue</span>'
    ];

    return $badges[$status] ?? '<span class="status">' . ucfirst($status) . '</span>';
}

function logActivite($action, $details, $utilisateur_id = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO logs_activite (action, details, utilisateur_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $action,
            json_encode($details),
            $utilisateur_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Erreur logActivite: " . $e->getMessage());
    }
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateReference($prefix = 'REF') {
    return $prefix . '-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
}
?>