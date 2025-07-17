<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Permissions : Admin, Gérant (gestion complète), Commercial/Préparateur (lecture seule)
requireLogin(['Admin', 'Gérant', 'Commercial', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();
$permissions = getCurrentUserPermissions();

$message = '';
$error = '';

// Traitement de la suppression - Seulement Admin et Gérant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    if (!canDelete('products')) {
        $error = "Vous n'avez pas l'autorisation de supprimer des produits";
    } else {
        try {
            $id = (int)($_POST['id'] ?? 0);
            
            // Vérifier si le produit est utilisé dans des commandes en cours
            $stmt = $manager->db->prepare("
                SELECT COUNT(*) FROM details_commandes dc
                JOIN commandes c ON dc.commande_id = c.id
                WHERE dc.produit_id = ? AND c.statut NOT IN ('livree', 'annulee')
            ");
            $stmt->execute([$id]);
            $commandes_actives = $stmt->fetchColumn();
            
            if ($commandes_actives > 0) {
                throw new Exception("Impossible de supprimer ce lot car il est utilisé dans $commandes_actives commande(s) en cours");
            }
            
            // Récupérer le nom pour le message
            $stmt = $manager->db->prepare("SELECT nom, reference FROM produits WHERE id = ?");
            $stmt->execute([$id]);
            $produit = $stmt->fetch();
            
            // Marquer comme inactif au lieu de supprimer
            $stmt = $manager->db->prepare("UPDATE produits SET actif = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log de l'activité
            logActivity('delete_product', [
                'product_id' => $id,
                'product_name' => $produit['nom'],
                'product_reference' => $produit['reference']
            ]);
            
            $message = "Lot '{$produit['nom']}' ({$produit['reference']}) supprimé avec succès";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Récupération des filtres
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$statut_stock = $_GET['statut_stock'] ?? '';

// Récupération des données
$produits = $manager->getProduits($search, $category, $statut_stock);
$categories = $manager->getCategories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des lots - SOTA Fashion</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container dashboard-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="main-content dashboard-main">
            <div class="top-bar">
                <div class="user-info">
                    <span>Bonjour, <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></span>
                    <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <a href="../../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h1><i class="fas fa-boxes"></i> Gestion des lots</h1>
                <p class="dashboard-subtitle">
                    <?php if ($permissions['can_manage_products']): ?>
                        Gérez vos lots de vêtements avec leurs différentes tailles
                    <?php else: ?>
                        Consultez les lots de vêtements et leurs stocks
                    <?php endif; ?>
                    - <?= count($produits) ?> lots disponibles
                </p>
            </section>

            <?php if ($message): ?>
                <div class="dashboard-section" style="background: #d4edda; border-left: 4px solid #28a745; margin: 20px 30px;">
                    <p style="color: #155724; margin: 0;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="dashboard-section" style="background: #f8d7da; border-left: 4px solid #dc3545; margin: 20px 30px;">
                    <p style="color: #721c24; margin: 0;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- Mode lecture seule pour Commercial/Préparateur -->
            <?php if (!$permissions['can_manage_products']): ?>
                <div class="dashboard-section" style="background: #e3f2fd; border-left: 4px solid #2196f3; margin: 20px 30px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="fas fa-info-circle" style="color: #2196f3; font-size: 20px;"></i>
                        <div>
                            <strong style="color: #1976d2;">Mode consultation</strong>
                            <p style="margin: 5px 0 0 0; color: #1565c0; font-size: 0.9em;">
                                Vous consultez les lots en lecture seule. 
                                <?php if ($user['role'] === 'Commercial'): ?>
                                    Utilisez ces informations pour vos commandes clients.
                                <?php elseif ($user['role'] === 'Préparateur'): ?>
                                    Utilisez ces informations pour la gestion des stocks.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filtres et recherche -->
            <section class="dashboard-section">
                <h2><i class="fas fa-filter"></i> Filtres et recherche</h2>
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher un lot (nom, référence, marque...)" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <select name="category">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="statut_stock">
                        <option value="">Tous les statuts de stock</option>
                        <option value="normal" <?= $statut_stock === 'normal' ? 'selected' : '' ?>>Stock normal</option>
                        <option value="alerte" <?= $statut_stock === 'alerte' ? 'selected' : '' ?>>Stock en alerte</option>
                        <option value="rupture" <?= $statut_stock === 'rupture' ? 'selected' : '' ?>>Rupture de stock</option>
                    </select>

                    <button type="submit" class="btn-orange">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                    
                    <a href="produits.php" class="btn-border">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                </form>
            </section>

            <!-- Actions rapides - Selon permissions -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <?php if ($permissions['can_manage_products']): ?>
                        <a href="nouveau.php" class="btn-orange">
                            <i class="fas fa-plus"></i> Nouveau lot
                        </a>
                        <a href="../categories/categories.php" class="btn-border">
                            <i class="fas fa-tags"></i> Gérer les catégories
                        </a>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'Préparateur'): ?>
                        <a href="../stocks/stocks.php" class="btn-orange">
                            <i class="fas fa-warehouse"></i> Gérer les stocks
                        </a>
                        <a href="../stocks/mouvement.php" class="btn-border">
                            <i class="fas fa-exchange-alt"></i> Mouvement de stock
                        </a>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'Commercial'): ?>
                        <a href="../commandes/nouvelle.php" class="btn-orange">
                            <i class="fas fa-shopping-cart"></i> Nouvelle commande
                        </a>
                        <a href="../commandes/commandes.php" class="btn-border">
                            <i class="fas fa-list"></i> Mes commandes
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Info sur les lots -->
            <div class="dashboard-section" style="background: #e3f2fd; border-left: 4px solid #2196f3; margin: 20px 30px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-info-circle" style="color: #2196f3; font-size: 24px;"></i>
                    <div>
                        <h3 style="margin: 0 0 8px 0; color: #1976d2;">Gestion par lots</h3>
                        <p style="margin: 0; color: #1565c0;">
                            Chaque lot contient plusieurs tailles d'un même article (XS, S, M, L, XL, XXL). 
                            Le stock indiqué représente le nombre total d'unités disponibles toutes tailles confondues.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Liste des lots -->
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Référence du lot</th>
                        <th>Nom de l'article</th>
                        <th>Catégorie</th>
                        <th>Tailles disponibles</th>
                        <th>Stock total</th>
                        <th>Prix unitaire</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 50px; color: #666;">
                                <i class="fas fa-boxes" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                                Aucun lot trouvé
                                <?php if (!$search && !$category && !$statut_stock && $permissions['can_manage_products']): ?>
                                    <br><a href="nouveau.php" class="btn-orange" style="margin-top: 15px;">
                                        <i class="fas fa-plus"></i> Créer le premier lot
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td>
                                    <strong style="color: #ff6b35;"><?= htmlspecialchars($produit['reference']) ?></strong>
                                    <?php if ($produit['marque']): ?>
                                        <br><small style="color: #666;"><?= htmlspecialchars($produit['marque']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($produit['nom']) ?></strong>
                                    <?php if ($produit['couleur']): ?>
                                        <br><span style="color: #666; font-size: 0.9em;">
                                            <i class="fas fa-palette"></i> <?= htmlspecialchars($produit['couleur']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status actif">
                                        <?= htmlspecialchars($produit['categorie_nom'] ?? 'Non classé') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($produit['taille']): ?>
                                        <?php 
                                        $tailles_courantes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                        $taille_principale = htmlspecialchars($produit['taille']);
                                        ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <?php foreach ($tailles_courantes as $taille): ?>
                                                <span style="padding: 2px 6px; background: <?= $taille === $taille_principale ? '#ff6b35' : '#f0f0f0' ?>; 
                                                             color: <?= $taille === $taille_principale ? 'white' : '#666' ?>; 
                                                             border-radius: 3px; font-size: 0.8em; font-weight: 500;">
                                                    <?= $taille ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <small style="color: #666; margin-top: 4px; display: block;">
                                            <i class="fas fa-star" style="color: #ff6b35;"></i> Principale: <?= $taille_principale ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">Taille unique</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: bold; font-size: 1.1em; color: <?= $produit['statut_stock'] === 'rupture' ? '#e74c3c' : ($produit['statut_stock'] === 'alerte' ? '#f39c12' : '#27ae60') ?>;">
                                        <?= $produit['stock_actuel'] ?>
                                    </span>
                                    <span style="color: #666;"> unités</span>
                                    <br><small style="color: #666;">Seuil: <?= $produit['seuil_minimum'] ?></small>
                                </td>
                                <td>
                                    <strong style="color: #ff6b35; font-size: 1.1em;">
                                        <?= number_format($produit['prix_vente'], 2) ?>€
                                    </strong>
                                    <br><small style="color: #666;">par unité</small>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = ['normal' => 'actif', 'alerte' => 'alerte', 'rupture' => 'rupture'];
                                    $status_labels = ['normal' => 'Stock OK', 'alerte' => 'Stock bas', 'rupture' => 'Rupture'];
                                    ?>
                                    <span class="status <?= $status_colors[$produit['statut_stock']] ?>">
                                        <?= $status_labels[$produit['statut_stock']] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <!-- Actions selon le rôle -->
                                        <?php if ($user['role'] === 'Préparateur'): ?>
                                            <a href="../stocks/mouvement.php?produit=<?= $produit['id'] ?>" 
                                               class="btn-orange btn-small" title="Ajuster le stock">
                                                <i class="fas fa-exchange-alt"></i> Stock
                                            </a>
                                            <a href="../stocks/historique.php?produit=<?= $produit['id'] ?>" 
                                               class="btn-border btn-small" title="Voir l'historique">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($user['role'] === 'Commercial'): ?>
                                            <a href="../commandes/nouvelle.php?produit_id=<?= $produit['id'] ?>" 
                                               class="btn-orange btn-small" title="Commander ce produit">
                                                <i class="fas fa-shopping-cart"></i> Commander
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_manage_products']): ?>
                                            <a href="modifier.php?id=<?= $produit['id'] ?>" 
                                               class="btn-border btn-small" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (canDelete('products')): ?>
                                            <button type="button" 
                                                    class="btn-danger btn-small" 
                                                    onclick="confirmerSuppression(<?= $produit['id'] ?>, '<?= htmlspecialchars($produit['nom']) ?>', '<?= htmlspecialchars($produit['reference']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Légende des tailles -->
            <div class="dashboard-section" style="margin: 20px 30px; background: #f8f9fa;">
                <h3 style="margin: 0 0 15px 0; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-ruler"></i> Guide des tailles
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.9em;">
                    <div><strong>XS :</strong> Extra Small (32-34)</div>
                    <div><strong>S :</strong> Small (36-38)</div>
                    <div><strong>M :</strong> Medium (40-42)</div>
                    <div><strong>L :</strong> Large (44-46)</div>
                    <div><strong>XL :</strong> Extra Large (48-50)</div>
                    <div><strong>XXL :</strong> Double XL (52-54)</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de confirmation - Seulement si permissions de suppression -->
    <?php if (canDelete('products')): ?>
    <div id="modalSuppression" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3 style="margin: 0; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</h3>
            </div>
            <div class="modal-body" style="margin-bottom: 30px;">
                <p>Êtes-vous sûr de vouloir supprimer le lot "<span id="nomProduit"></span>" ?</p>
                <p style="color: #666;"><strong>Référence :</strong> <span id="referenceProduit"></span></p>
                <p style="color: #666;"><strong>Cette action est irréversible.</strong></p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-border" onclick="fermerModal()">Annuler</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="idProduit">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Supprimer définitivement
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmerSuppression(id, nom, reference) {
            document.getElementById('idProduit').value = id;
            document.getElementById('nomProduit').textContent = nom;
            document.getElementById('referenceProduit').textContent = reference;
            document.getElementById('modalSuppression').style.display = 'flex';
        }

        function fermerModal() {
            document.getElementById('modalSuppression').style.display = 'none';
        }

        document.getElementById('modalSuppression').addEventListener('click', function(e) {
            if (e.target === this) fermerModal();
        });
    </script>
    <?php endif; ?>
</body>
</html>