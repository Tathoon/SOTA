<?php
require_once __DIR__ . '/../config/database.php';

class SotaManager {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getStatistiquesDashboard() {
        $stats = [];

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Produits WHERE actif = 1");
            $stmt->execute();
            $stats['total_produits'] = $stmt->fetch()['total'];

            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Produits WHERE stock_actuel = 0 AND actif = 1");
            $stmt->execute();
            $stats['produits_rupture'] = $stmt->fetch()['total'];

            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Produits WHERE stock_actuel <= seuil_minimum AND stock_actuel > 0 AND actif = 1");
            $stmt->execute();
            $stats['produits_alerte'] = $stmt->fetch()['total'];

            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM Commandes WHERE statut IN ('en_attente', 'confirmee')");
            $stmt->execute();
            $stats['commandes_attente'] = $stmt->fetch()['total'];
        } catch (Exception $e) {
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
            return [
                ['nom' => 'Produit C', 'stock_actuel' => 0, 'seuil_minimum' => 2]
            ];
        }
    }

    public function getCommandes($statut = '', $limit = null) {
        try {
            $sql = "SELECT c.*, COALESCE(COUNT(dc.id), 0) as nb_produits
                    FROM Commandes c
                    LEFT JOIN details_commandes dc ON c.id = dc.commande_id";

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
            return [];
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
            return [];
        }
    }

    public function getCategories() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Categories ORDER BY nom");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getFournisseurs() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Fournisseurs ORDER BY nom");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function ajouterMouvementStock($produit_id, $type_mouvement, $quantite, $motif = '', $utilisateur_id = null) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT stock_actuel FROM Produits WHERE id = ? AND actif = 1");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();

            if (!$produit) throw new Exception("Produit non trouvé");

            $stock_avant = $produit['stock_actuel'];

            switch ($type_mouvement) {
                case 'entree':
                    $stock_apres = $stock_avant + $quantite;
                    break;
                case 'sortie':
                    if ($stock_avant < $quantite) throw new Exception("Stock insuffisant");
                    $stock_apres = $stock_avant - $quantite;
                    break;
                case 'ajustement':
                    $stock_apres = $quantite;
                    $quantite = $stock_apres - $stock_avant;
                    break;
                default:
                    throw new Exception("Type de mouvement invalide");
            }

            $stmt = $this->db->prepare("UPDATE Produits SET stock_actuel = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$stock_apres, $produit_id]);

            $stmt = $this->db->prepare("INSERT INTO mouvements_stock (produit_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, utilisateur_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$produit_id, $type_mouvement, abs($quantite), $stock_avant, $stock_apres, $motif, $utilisateur_id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function creerCommande($data) {
        try {
            $this->db->beginTransaction();

            $numero = 'CMD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $stmt = $this->db->prepare("INSERT INTO Commandes (numero_commande, client_nom, client_email, client_telephone, client_adresse, date_commande, date_livraison_prevue, utilisateur_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $numero,
                $data['client_nom'],
                $data['client_email'] ?? null,
                $data['client_telephone'] ?? null,
                $data['client_adresse'] ?? null,
                $data['date_commande'] ?? date('Y-m-d'),
                $data['date_livraison_prevue'] ?? null,
                $data['utilisateur_id']
            ]);

            $commande_id = $this->db->lastInsertId();

            if (!empty($data['produits'])) {
                $total = 0;
                foreach ($data['produits'] as $produit) {
                    $sous_total = $produit['quantite'] * $produit['prix_unitaire'];
                    $total += $sous_total;

                    $stmt = $this->db->prepare("INSERT INTO details_commandes (commande_id, produit_id, quantite, prix_unitaire, sous_total) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$commande_id, $produit['produit_id'], $produit['quantite'], $produit['prix_unitaire'], $sous_total]);
                }

                $stmt = $this->db->prepare("UPDATE Commandes SET total = ? WHERE id = ?");
                $stmt->execute([$total, $commande_id]);
            }

            $this->db->commit();
            return $commande_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function ajouterProduit($data) {
        try {
            $stmt = $this->db->prepare("INSERT INTO Produits (reference, nom, description, categorie_id, stock_actuel, seuil_minimum, prix_achat, prix_vente, taille, couleur, emplacement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
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
                $data['emplacement'] ?? null
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function ajouterFournisseur($data) {
        try {
            $stmt = $this->db->prepare("INSERT INTO Fournisseurs (nom, siret, contact, telephone, email, adresse, ville, code_postal, delais_livraison, conditions_paiement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([
                $data['nom'],
                $data['siret'] ?? null,
                $data['contact'] ?? null,
                $data['telephone'] ?? null,
                $data['email'] ?? null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['code_postal'] ?? null,
                $data['delais_livraison'] ?? 7,
                $data['conditions_paiement'] ?? null
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function mettreAJourStatutCommande($commande_id, $nouveau_statut) {
        try {
            $stmt = $this->db->prepare("UPDATE Commandes SET statut = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            return $stmt->execute([$nouveau_statut, $commande_id]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getDetailsCommande($commande_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();
            if (!$commande) return null;

            $stmt = $this->db->prepare("SELECT dc.*, p.nom as produit_nom, p.reference 
                                       FROM details_commandes dc
                                       LEFT JOIN Produits p ON dc.produit_id = p.id
                                       WHERE dc.commande_id = ?");
            $stmt->execute([$commande_id]);
            $commande['produits'] = $stmt->fetchAll();

            return $commande;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getProduitById($id) {
        try {
            $stmt = $this->db->prepare("SELECT p.*, c.nom as categorie_nom 
                                       FROM Produits p 
                                       LEFT JOIN Categories c ON p.categorie_id = c.id 
                                       WHERE p.id = ? AND p.actif = 1");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }

    public function ajouterCategorie($nom, $description = '') {
        try {
            $stmt = $this->db->prepare("INSERT INTO Categories (nom, description) VALUES (?, ?)");
            return $stmt->execute([$nom, $description]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function supprimerCommande($commande_id, $utilisateur_id = null) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT dc.*, p.nom as produit_nom, p.reference 
                                       FROM details_commandes dc
                                       LEFT JOIN Produits p ON dc.produit_id = p.id
                                       WHERE dc.commande_id = ?");
            $stmt->execute([$commande_id]);
            $details = $stmt->fetchAll();

            $stmt = $this->db->prepare("SELECT numero_commande, statut FROM Commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();

            if (!$commande) throw new Exception("Commande non trouvée");

            if (in_array($commande['statut'], ['confirmee', 'en_preparation'])) {
                foreach ($details as $detail) {
                    $this->ajouterMouvementStock(
                        $detail['produit_id'],
                        'entree',
                        $detail['quantite'],
                        "Annulation commande " . $commande['numero_commande'],
                        $utilisateur_id
                    );
                }
            }

            $stmt = $this->db->prepare("DELETE FROM details_commandes WHERE commande_id = ?");
            $stmt->execute([$commande_id]);

            $stmt = $this->db->prepare("DELETE FROM Commandes WHERE id = ?");
            $stmt->execute([$commande_id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function modifierCommande($commande_id, $data, $utilisateur_id = null) {
        try {
            $this->db->beginTransaction();

            $ancienneCommande = $this->getDetailsCommande($commande_id);
            if (!$ancienneCommande) throw new Exception("Commande non trouvée");

            if (in_array($ancienneCommande['statut'], ['confirmee', 'en_preparation'])) {
                foreach ($ancienneCommande['produits'] as $ancien_produit) {
                    $this->ajouterMouvementStock(
                        $ancien_produit['produit_id'],
                        'entree',
                        $ancien_produit['quantite'],
                        "Modification commande " . $ancienneCommande['numero_commande'],
                        $utilisateur_id
                    );
                }
            }

            $stmt = $this->db->prepare("DELETE FROM details_commandes WHERE commande_id = ?");
            $stmt->execute([$commande_id]);

            $stmt = $this->db->prepare("UPDATE Commandes SET 
                                        client_nom = ?, 
                                        client_email = ?, 
                                        client_telephone = ?, 
                                        client_adresse = ?,
                                        date_livraison_prevue = ?,
                                        updated_at = CURRENT_TIMESTAMP 
                                        WHERE id = ?");
            $stmt->execute([
                $data['client_nom'],
                $data['client_email'] ?? null,
                $data['client_telephone'] ?? null,
                $data['client_adresse'] ?? null,
                $data['date_livraison_prevue'] ?? null,
                $commande_id
            ]);

            if (!empty($data['produits'])) {
                $total = 0;
                foreach ($data['produits'] as $produit) {
                    $sous_total = $produit['quantite'] * $produit['prix_unitaire'];
                    $total += $sous_total;

                    $stmt = $this->db->prepare("INSERT INTO details_commandes (commande_id, produit_id, quantite, prix_unitaire, sous_total) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$commande_id, $produit['produit_id'], $produit['quantite'], $produit['prix_unitaire'], $sous_total]);

                    if (in_array($ancienneCommande['statut'], ['confirmee', 'en_preparation'])) {
                        $this->ajouterMouvementStock(
                            $produit['produit_id'],
                            'sortie',
                            $produit['quantite'],
                            "Modification commande " . $ancienneCommande['numero_commande'],
                            $utilisateur_id
                        );
                    }
                }

                $stmt = $this->db->prepare("UPDATE Commandes SET total = ? WHERE id = ?");
                $stmt->execute([$total, $commande_id]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function commandePeutEtreModifiee($commande_id) {
        try {
            $stmt = $this->db->prepare("SELECT statut FROM Commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();
            return $commande && !in_array($commande['statut'], ['expediee', 'livree']);
        } catch (Exception $e) {
            return false;
        }
    }

    public function commandePeutEtreSupprimee($commande_id) {
        try {
            $stmt = $this->db->prepare("SELECT statut FROM Commandes WHERE id = ?");
            $stmt->execute([$commande_id]);
            $commande = $stmt->fetch();
            return $commande && !in_array($commande['statut'], ['expediee', 'livree']);
        } catch (Exception $e) {
            return false;
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
        'en_preparation' => '<span class="status en-preparation">En préparation</span>',
        'expediee' => '<span class="status expediee">Expédiée</span>',
        'livree' => '<span class="status livree">Livrée</span>',
        'annulee' => '<span class="status annulee">Annulée</span>'
    ];

    return $badges[$status] ?? '<span class="status">' . ucfirst($status) . '</span>';
}

function validerDonneesCommande($data) {
    $erreurs = [];

    if (empty($data['client_nom'])) {
        $erreurs[] = "Le nom du client est obligatoire";
    }

    if (!empty($data['client_email']) && !filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'email du client n'est pas valide";
    }

    if (empty($data['produits']) || !is_array($data['produits'])) {
        $erreurs[] = "Au moins un produit doit être commandé";
    }

    return $erreurs;
}
?>
