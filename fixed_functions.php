<?php
/**
 * Version corrigée de functions.php avec gestion d'erreurs améliorée
 */

// Inclure la configuration
require_once __DIR__ . '/config.php';

class SotaManager {
    private $db;

    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Erreur de connexion à la base de données : " . $e->getMessage());
            } else {
                die("Erreur de connexion à la base de données");
            }
        }
    }

    /**
     * Ajouter un produit avec gestion d'erreurs améliorée
     */
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

            // Vérifier que les colonnes existent avant insertion
            $columns = $this->getTableColumns('produits');
            
            // Construction dynamique de la requête en fonction des colonnes disponibles
            $fields = [
                'reference' => $data['reference'],
                'nom' => $data['nom'],
                'prix_vente' => $data['prix_vente']
            ];

            // Ajouter les champs optionnels seulement s'ils existent dans la table
            $optional_fields = [
                'description' => $data['description'] ?? null,
                'categorie_id' => $data['categorie_id'] ?? null,
                'stock_actuel' => $data['stock_actuel'] ?? 0,
                'seuil_minimum' => $data['seuil_minimum'] ?? 5,
                'prix_achat' => $data['prix_achat'] ?? null,
                'taille' => $data['taille'] ?? null,
                'couleur' => $data['couleur'] ?? null,
                'emplacement' => $data['emplacement'] ?? null,
                'lot_minimum' => $data['lot_minimum'] ?? 1,
                'poids' => $data['poids'] ?? null,
                'composition' => $data['composition'] ?? null,
                'saison' => $data['saison'] ?? null,
                'marque' => $data['marque'] ?? null,
                'collection' => $data['collection'] ?? null
            ];

            foreach ($optional_fields as $field => $value) {
                if (in_array($field, $columns)) {
                    $fields[$field] = $value;
                }
            }

            // Construction de la requête
            $field_names = array_keys($fields);
            $placeholders = array_fill(0, count($fields), '?');
            
            $sql = "INSERT INTO produits (" . implode(', ', $field_names) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            
            $result = $stmt->execute(array_values($fields));
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
            error_log("Erreur ajouterProduit: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir les colonnes d'une table
     */
    private function getTableColumns($table) {
        try {
            $stmt = $this->db->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $columns;
        } catch (Exception $e) {
            error_log("Erreur getTableColumns: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Modifier un produit avec gestion d'erreurs
     */
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

            // Obtenir les colonnes disponibles
            $columns = $this->getTableColumns('produits');
            
            // Construction dynamique de la requête de mise à jour
            $fields = [];
            $values = [];

            $field_mapping = [
                'reference' => $data['reference'],
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
                'categorie_id' => $data['categorie_id'] ?? null,
                'seuil_minimum' => $data['seuil_minimum'] ?? 5,
                'prix_achat' => $data['prix_achat'] ?? null,
                'prix_vente' => $data['prix_vente'],
                'taille' => $data['taille'] ?? null,
                'couleur' => $data['couleur'] ?? null,
                'emplacement' => $data['emplacement'] ?? null,
                'lot_minimum' => $data['lot_minimum'] ?? 1,
                'poids' => $data['poids'] ?? null,
                'composition' => $data['composition'] ?? null,
                'saison' => $data['saison'] ?? null,
                'marque' => $data['marque'] ?? null,
                'collection' => $data['collection'] ?? null
            ];

            foreach ($field_mapping as $field => $value) {
                if (in_array($field, $columns)) {
                    $fields[] = "$field = ?";
                    $values[] = $value;
                }
            }

            // Ajouter updated_at si la colonne existe
            if (in_array('updated_at', $columns)) {
                $fields[] = "updated_at = CURRENT_TIMESTAMP";
            }

            $values[] = $id; // Pour la clause WHERE

            $sql = "UPDATE produits SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur modifierProduit: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ajouter un mouvement de stock avec gestion d'erreurs
     */
    public function ajouterMouvementStock($produit_id, $type, $quantite, $motif = '', $utilisateur_id = null, $reference_document = null) {
        try {
            $this->db->beginTransaction();

            // Récupérer le stock actuel
            $stmt = $this->db->prepare("SELECT stock_actuel FROM produits WHERE id = ? AND actif = 1");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();

            if (!$produit) {
                throw new Exception("Produit non trouvé ou inactif");
            }

            $stock_avant = $produit['stock_actuel'];
            
            // Calculer le nouveau stock
            switch ($type) {
                case 'entree':
                    $stock_apres = $stock_avant + $quantite;
                    break;
                case 'sortie':
                    if ($stock_avant < $quantite) {
                        throw new Exception("Stock insuffisant (disponible: $stock_avant, demandé: $quantite)");
                    }
                    $stock_apres = $stock_avant - $quantite;
                    break;
                case 'ajustement':
                    $stock_apres = $quantite; // Quantité absolue
                    $quantite = $stock_apres - $stock_avant; // Différence
                    break;
                default:
                    throw new Exception("Type de mouvement invalide");
            }

            // Vérifier les colonnes disponibles pour les mouvements
            $columns = $this->getTableColumns('mouvements_stock');
            
            // Préparer les données d'insertion
            $fields = [
                'produit_id' => $produit_id,
                'type_mouvement' => $type,
                'quantite' => abs($quantite),
                'quantite_avant' => $stock_avant,
                'quantite_apres' => $stock_apres,
                'motif' => $motif
            ];

            // Ajouter les champs optionnels s'ils existent
            if (in_array('utilisateur_id', $columns) && $utilisateur_id) {
                $fields['utilisateur_id'] = $utilisateur_id;
            }
            if (in_array('reference_document', $columns) && $reference_document) {
                $fields['reference_document'] = $reference_document;
            }

            // Insertion du mouvement
            $field_names = array_keys($fields);
            $placeholders = array_fill(0, count($fields), '?');
            
            $sql = "INSERT INTO mouvements_stock (" . implode(', ', $field_names) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_values($fields));

            // Mettre à jour le stock du produit
            $stmt = $this->db->prepare("UPDATE produits SET stock_actuel = ? WHERE id = ?");
            $stmt->execute([$stock_apres, $produit_id]);

            $this->db->commit();
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur ajouterMouvementStock: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir un produit par ID avec gestion d'erreurs
     */
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

    /**
     * Diagnostic de la base de données
     */
    public function diagnostiquerBase() {
        $diagnostic = [
            'tables' => [],
            'erreurs' => []
        ];

        $tables_requises = ['produits', 'commandes', 'mouvements_stock', 'categories', 'fournisseurs'];

        foreach ($tables_requises as $table) {
            try {
                $stmt = $this->db->prepare("DESCRIBE $table");
                $stmt->execute();
                $colonnes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $diagnostic['tables'][$table] = $colonnes;
            } catch (Exception $e) {
                $diagnostic['erreurs'][] = "Table $table: " . $e->getMessage();
            }
        }

        return $diagnostic;
    }

    // ... Autres méthodes existantes ...
    // (Gardez toutes vos autres méthodes existantes)
}

/**
 * Fonctions utilitaires
 */
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' €';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'en_attente' => '<span class="status-badge status-pending">En attente</span>',
        'confirmee' => '<span class="status-badge status-confirmed">Confirmée</span>',
        'en_preparation' => '<span class="status-badge status-preparing">En préparation</span>',
        'expediee' => '<span class="status-badge status-shipped">Expédiée</span>',
        'livree' => '<span class="status-badge status-delivered">Livrée</span>',
        'annulee' => '<span class="status-badge status-cancelled">Annulée</span>'
    ];
    
    return $badges[$status] ?? '<span class="status-badge">Inconnu</span>';
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function logActivite($action, $details, $user_id = null) {
    try {
        $manager = new SotaManager();
        $stmt = $manager->db->prepare("
            INSERT INTO logs_activite (action, details, utilisateur_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $action,
            json_encode($details),
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Erreur logActivite: " . $e->getMessage());
    }
}
?>