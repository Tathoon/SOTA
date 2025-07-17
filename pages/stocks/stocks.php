<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    try {
        $id = (int)$_POST['id'];
        
        // Vérifier si le produit est utilisé dans des commandes en cours
        $stmt = $manager->db->prepare("
            SELECT COUNT(*) FROM details_commandes dc
            JOIN commandes c ON dc.commande_id = c.id
            WHERE dc.produit_id = ? AND c.statut NOT IN ('livree', 'annulee')
        ");
        $stmt->execute([$id]);
        $commandes_actives = $stmt->fetchColumn();
        
        if ($commandes_actives > 0) {
            throw new Exception("Impossible de supprimer ce produit car il est utilisé dans $commandes_actives commande(s) en cours");
        }
        
        // Récupérer le nom pour le message
        $stmt = $manager->db->prepare("SELECT nom, reference FROM produits WHERE id = ?");
        $stmt->execute([$id]);
        $produit = $stmt->fetch();
        
        // Marquer comme inactif au lieu de supprimer
        $stmt = $manager->db->prepare("UPDATE produits SET actif = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = "Produit '{$produit['nom']}' ({$produit['reference']}) supprimé avec succès";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des filtres
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$filtre = $_GET['filtre'] ?? '';

// Récupération des produits avec filtres
$produits = $manager->getProduits($search, $category, $filtre);
$categories = $manager->getCategories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des stocks - SOTA Fashion</title>
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
                <h1><i class="fas fa-warehouse"></i> Gestion des stocks</h1>
                <p class="dashboard-subtitle">
                    Suivez et gérez vos stocks en temps réel - <?= count($produits) ?> produits
                    <?= $search ? ' pour "' . htmlspecialchars($search) . '"' : '' ?>
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

            <!-- Filtres et recherche -->
            <section class="dashboard-section">
                <h2><i class="fas fa-filter"></i> Filtres et recherche</h2>
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher un produit (nom, référence...)" 
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

                    <select name="filtre">
                        <option value="">Tous les stocks</option>
                        <option value="normal" <?= $filtre === 'normal' ? 'selected' : '' ?>>Stock normal</option>
                        <option value="alerte" <?= $filtre === 'alerte' ? 'selected' : '' ?>>Stock critique</option>
                        <option value="rupture" <?= $filtre === 'rupture' ? 'selected' : '' ?>>Rupture</option>
                    </select>

                    <button type="submit" class="btn-orange">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                    
                    <a href="stocks.php" class="btn-border">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                </form>
            </section>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="mouvement.php" class="btn-orange">
                        <i class="fas fa-exchange-alt"></i> Nouveau mouvement
                    </a>
                    <a href="historique.php" class="btn-border">
                        <i class="fas fa-history"></i> Historique
                    </a>
                    <a href="../produits/produits.php" class="btn-border">
                        <i class="fas fa-boxes"></i> Gérer les lots
                    </a>
                </div>
            </section>

            <!-- Liste des stocks -->
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th>Stock actuel</th>
                        <th>Seuil minimum</th>
                        <th>Emplacement</th>
                        <th>Valeur stock</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 50px; color: #666;">
                                <i class="fas fa-warehouse" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                                Aucun produit trouvé
                                <?= $search || $category || $filtre ? '<br>Aucun produit ne correspond à vos critères.' : '<br>Votre stock est vide.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($produit['nom']) ?></strong>
                                    <br><small style="color: #666;"><?= htmlspecialchars($produit['reference']) ?></small>
                                    <?php if ($produit['marque']): ?>
                                        <br><small style="color: #888;"><?= htmlspecialchars($produit['marque']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status actif">
                                        <?= htmlspecialchars($produit['categorie_nom'] ?? 'Non classé') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stock-quantity">
                                        <span class="quantity-value <?= $produit['statut_stock'] ?>" 
                                              style="font-weight: bold; font-size: 1.2em;">
                                            <?= $produit['stock_actuel'] ?>
                                        </span>
                                        <small style="color: #666;"> unités</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="threshold-info">
                                        <span><?= $produit['seuil_minimum'] ?></span>
                                        <small style="color: #666;"> seuil</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($produit['emplacement']): ?>
                                        <span class="location-badge" style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-size: 0.9em;">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($produit['emplacement']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">Non défini</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $valeur_stock = $produit['stock_actuel'] * ($produit['prix_vente'] ?? 0);
                                    ?>
                                    <strong style="color: #ff6b35;">
                                        <?= number_format($valeur_stock, 2) ?>€
                                    </strong>
                                    <?php if ($produit['prix_vente']): ?>
                                        <br><small style="color: #666;">
                                            <?= number_format($produit['prix_vente'], 2) ?>€/unité
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'actif';
                                    $status_text = 'Normal';
                                    
                                    if ($produit['statut_stock'] === 'rupture') {
                                        $status_class = 'rupture';
                                        $status_text = 'Rupture';
                                    } elseif ($produit['statut_stock'] === 'alerte') {
                                        $status_class = 'alerte';
                                        $status_text = 'Stock bas';
                                    }
                                    ?>
                                    <span class="status <?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="mouvement.php?produit=<?= $produit['id'] ?>" 
                                           class="btn-orange btn-small" 
                                           title="Ajuster le stock">
                                            <i class="fas fa-exchange-alt"></i> Stock
                                        </a>
                                        
                                        <a href="historique.php?produit=<?= $produit['id'] ?>" 
                                           class="btn-border btn-small" 
                                           title="Voir l'historique">
                                            <i class="fas fa-history"></i> Historique
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn-danger btn-small" 
                                                onclick="confirmerSuppression(<?= $produit['id'] ?>, '<?= htmlspecialchars($produit['nom']) ?>', '<?= htmlspecialchars($produit['reference']) ?>')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="modalSuppression" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3 style="margin: 0; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</h3>
            </div>
            <div class="modal-body" style="margin-bottom: 30px;">
                <p>Êtes-vous sûr de vouloir supprimer le produit "<span id="nomProduit"></span>" ?</p>
                <p style="color: #666;"><strong>Référence :</strong> <span id="referenceProduit"></span></p>
                <p style="color: #666;"><strong>Attention :</strong> Cette action supprimera également l'historique des stocks de ce produit.</p>
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

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('modalSuppression').addEventListener('click', function(e) {
            if (e.target === this) {
                fermerModal();
            }
        });
    </script>
</body>
</html>