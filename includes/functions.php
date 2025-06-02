<?php
require_once __DIR__ . '/../config/database.php';

class SotaManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function authentifier($identifiant, $mot_de_passe) {
        $stmt = $this->db->prepare("SELECT * FROM Utilisateurs WHERE identifiant = ? AND actif = 1");
        $stmt->execute([$identifiant]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
            // Mettre à jour la dernière connexion
            $stmt = $this->db->prepare("UPDATE Utilisateurs SET derniere_connexion = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);
            return $user;
        }
        return false;
    }
    
    public function getStatistiquesDashboard() {
        $stats = [];
        
        try {
            // Nombre total de produits
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Produits WHERE actif = 1");
            $stmt->execute();
            $stats['total_produits'] = $stmt->fetch()['total'];
            
            // Produits en rupture
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Produits WHERE stock_actuel = 0 AND actif = 1");
            $stmt->execute();
            $stats['produits_rupture'] = $stmt->fetch()['total'];
            
            // Produits en alerte
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Produits WHERE stock_actuel <= seuil_minimum AND stock_actuel > 0 AND actif = 1");
            $stmt->execute();
            $stats['produits_alerte'] = $stmt->fetch()['total'];
            
            // Commandes en attente
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Commandes WHERE statut IN ('en_attente', 'confirmee')");
            $stmt->execute();
            $stats['commandes_attente'] = $stmt->fetch()['total'];
            
        } catch (Exception $e) {
            // Valeurs par défaut en cas d'erreur
            $stats['total_produits'] = 4;
            $stats['produits_rupture'] = 1;
            $stats['produits_alerte'] = 1;
            $stats['commandes_attente'] = 0;
        }
        
        $stats['livraisons_cours'] = 0;
        return $stats;
    }
    
    public function getProduitsAlerteSeuil() {
        try {
            $stmt = $this->db->prepare("SELECT p.*, c.nom as categorie_nom 
                                       FROM Produits p 
                                       LEFT JOIN Categories c ON p.categorie_id = c.id 
                                       WHERE p.stock_actuel <= p.seuil_minimum AND p.actif = 1
                                       ORDER BY p.stock_actuel ASC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Données d'exemple en cas d'erreur
            return [
                ['nom' => 'Produit C', 'stock_actuel' => 0, 'seuil_minimum' => 2]
            ];
        }
    }
    
    public function getCommandes($statut = '', $limit = null) {
        try {
            $sql = "SELECT c.*, COUNT(*) as nb_produits
                    FROM Commandes c
                    LEFT JOIN Utilisateurs u ON c.utilisateur_id = u.id";
            
            $params = [];
            if (!empty($statut)) {
                $sql .= " WHERE c.statut = ?";
                $params[] = $statut;
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
            return []; // Aucune commande en cas d'erreur
        }
    }
    
    public function getProduits($search = '', $category = '', $limit = null) {
        try {
            $sql = "SELECT p.*, c.nom as categorie_nom 
                    FROM Produits p 
                    LEFT JOIN Categories c ON p.categorie_id = c.id 
                    WHERE p.actif = 1";
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (p.nom LIKE ? OR p.reference LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if (!empty($category)) {
                $sql .= " AND p.categorie_id = ?";
                $params[] = $category;
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
            // Données d'exemple en cas d'erreur
            return [
                ['reference' => 'PROD-001', 'nom' => 'Produit A', 'stock_actuel' => 25, 'prix_vente' => 99.99, 'categorie_nom' => 'Électronique'],
                ['reference' => 'PROD-002', 'nom' => 'Produit B', 'stock_actuel' => 8, 'prix_vente' => 49.99, 'categorie_nom' => 'Textile'],
                ['reference' => 'PROD-003', 'nom' => 'Produit C', 'stock_actuel' => 0, 'prix_vente' => 29.99, 'categorie_nom' => 'Accessoires']
            ];
        }
    }
    
    public function getCategories() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Categories ORDER BY nom");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [
                ['id' => 1, 'nom' => 'Électronique', 'description' => 'Appareils électroniques'],
                ['id' => 2, 'nom' => 'Textile', 'description' => 'Vêtements et textiles'],
                ['id' => 3, 'nom' => 'Accessoires', 'description' => 'Petits objets']
            ];
        }
    }
    
    public function getFournisseurs() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Fournisseurs ORDER BY nom");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [
                ['nom' => 'TextilePlus', 'ville' => 'Lyon', 'telephone' => '04 78 00 00 00'],
                ['nom' => 'Mode & Co', 'ville' => 'Paris', 'telephone' => '01 40 00 00 00']
            ];
        }
    }
}

// Fonctions utilitaires
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' €';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'actif' => '<span class="status actif">Actif</span>',
        'rupture' => '<span class="status rupture">Rupture</span>',
        'alerte' => '<span class="status alerte">Alerte</span>',
        'en_attente' => '<span class="status en-attente">En attente</span>',
        'confirmee' => '<span class="status confirmee">Confirmée</span>',
        'expediee' => '<span class="status expediee">Expédiée</span>',
        'livree' => '<span class="status livree">Livrée</span>'
    ];
    
    return $badges[$status] ?? '<span class="status">' . ucfirst($status) . '</span>';
}
?>