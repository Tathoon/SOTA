<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$filtre = $_GET['filtre'] ?? '';

// Convertir le filtre en statut_stock
$statut_stock = '';
switch ($filtre) {
    case 'critique':
        $statut_stock = 'alerte';
        break;
    case 'rupture':
        $statut_stock = 'rupture';
        break;
    case 'normal':
        $statut_stock = 'normal';
        break;
}

// Récupération des données
$produits = $manager->getProduits($search, $category, $statut_stock);
$categories = $manager->getCategories();

// Calcul des statistiques
$stats_stock = [
    'total' => count($manager->getProduits()),
    'normal' => count($manager->getProduits('', '', 'normal')),
    'alerte' => count($manager->getProduits('', '', 'alerte')),
    'rupture' => count($manager->getProduits('', '', 'rupture'))
];

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
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
                <p class="dashboard-subtitle">Suivi des stocks prêt-à-porter - <?= count($produits) ?> articles</p>
            </section>

            <?php if ($message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques de stock -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-tshirt icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats_stock['total'] ?></h3>
                        <p>Total produits</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle icon" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_stock['normal'] ?></h3>
                        <p>Stock normal</p>
                    </div>
                </div>
                <div class="stat-card alert">
                    <i class="fas fa-exclamation-triangle icon" style="color: #f39c12;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_stock['alerte'] ?></h3>
                        <p>Stock en alerte</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color: #e74c3c;">
                    <i class="fas fa-times-circle icon" style="color: #e74c3c;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_stock['rupture'] ?></h3>
                        <p>Rupture de stock</p>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher un produit..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <select name="category" class="filter-select">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filtre" class="filter-select">
                        <option value="">Tous les stocks</option>
                        <option value="normal" <?= $filtre === 'normal' ? 'selected' : '' ?>>Stock normal</option>
                        <option value="critique" <?= $filtre === 'critique' ? 'selected' : '' ?>>Stock critique</option>
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
                    <a href="export.php?format=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . $category : '' ?><?= $filtre ? '&filtre=' . $filtre : '' ?>" 
                       class="btn-border">
                        <i class="fas fa-download"></i> Exporter CSV
                    </a>
                </div>
            </section>

            <!-- Liste des stocks -->
            <div class="stock-table-container">
                <?php if (empty($produits)): ?>
                    <div class="empty-state">
                        <i class="fas fa-warehouse"></i>
                        <h3>Aucun produit trouvé</h3>
                        <p>
                            <?= $search || $category || $filtre ? 'Aucun produit ne correspond à vos critères.' : 'Votre stock est vide.' ?>
                        </p>
                    </div>
                <?php else: ?>
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
                            <?php foreach ($produits as $produit): ?>
                                <tr class="<?= $produit['statut_stock'] ?>">
                                    <td>
                                        <div class="stock-quantity">
                                            <span class="quantity-value <?= $produit['statut_stock'] ?>"><?= $produit['stock_actuel'] ?></span>
                                            <small>unités</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="threshold-info">
                                            <span><?= $produit['seuil_minimum'] ?></span>
                                            <small>seuil</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="location-badge">
                                            <?= $produit['emplacement'] ? htmlspecialchars($produit['emplacement']) : 'Non défini' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="stock-value">
                                            <?php if ($produit['prix_achat']): ?>
                                                <strong><?= formatPrice($produit['stock_actuel'] * $produit['prix_achat']) ?></strong>
                                                <small>Prix achat</small>
                                            <?php else: ?>
                                                <span style="color: #999;">N/A</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="status-with-indicator">
                                            <?= getStatusBadge($produit['statut_stock']) ?>
                                            <?php if ($produit['statut_stock'] === 'alerte'): ?>
                                                <div class="status-progress">
                                                    <div class="progress-bar" style="width: <?= ($produit['stock_actuel'] / $produit['seuil_minimum']) * 100 ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="mouvement.php?produit=<?= $produit['id'] ?>" class="btn-border btn-small" title="Mouvement stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            <a href="historique.php?produit=<?= $produit['id'] ?>" class="btn-border btn-small" title="Historique">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <a href="../produits/details.php?id=<?= $produit['id'] ?>" class="btn-border btn-small" title="Détails produit">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($produit['statut_stock'] === 'rupture' || $produit['statut_stock'] === 'alerte'): ?>
                                                <button onclick="reapprovisionnerRapide(<?= $produit['id'] ?>, '<?= htmlspecialchars($produit['nom']) ?>')" 
                                                        class="btn-warning btn-small" title="Réapprovisionner">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de réapprovisionnement rapide -->
    <div id="reapprovisionModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Réapprovisionnement rapide</h3>
                <button onclick="closeReapprovisionModal()" class="modal-close">&times;</button>
            </div>
            <form method="POST" action="mouvement.php" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="type_mouvement" value="entree">
                <input type="hidden" name="motif" value="Réapprovisionnement rapide">
                <input type="hidden" name="produit_id" id="modal_produit_id">
                
                <div class="form-group">
                    <label for="modal_produit_nom">Produit :</label>
                    <input type="text" id="modal_produit_nom" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="modal_quantite">Quantité à ajouter :</label>
                    <input type="number" name="quantite" id="modal_quantite" class="form-control" min="1" value="10" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_cout_unitaire">Coût unitaire (optionnel) :</label>
                    <input type="number" name="cout_unitaire" id="modal_cout_unitaire" class="form-control" step="0.01" min="0">
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeReapprovisionModal()" class="btn-border">Annuler</button>
                    <button type="submit" class="btn-orange">Confirmer</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .stock-table-container {
            margin: 0 30px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 0 30px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .product-info strong {
            color: #2c3e50;
            font-size: 14px;
        }

        .product-info small {
            color: #666;
            font-size: 12px;
        }

        .product-variants {
            display: flex;
            gap: 5px;
            margin: 5px 0;
        }

        .variant-badge {
            background: #f8f9fa;
            color: #495057;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            border: 1px solid #dee2e6;
        }

        .stock-quantity {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .quantity-value {
            font-size: 18px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 20px;
            min-width: 40px;
            text-align: center;
        }

        .quantity-value.normal {
            background: #d4edda;
            color: #155724;
        }

        .quantity-value.alerte {
            background: #fff3cd;
            color: #856404;
        }

        .quantity-value.rupture {
            background: #f8d7da;
            color: #721c24;
        }

        .threshold-info, .stock-value {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .threshold-info small, .stock-value small {
            color: #666;
            font-size: 11px;
        }

        .location-badge {
            background: #e9ecef;
            color: #495057;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-with-indicator {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .status-progress {
            width: 60px;
            height: 4px;
            background: #f8f9fa;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--warning-color);
            transition: width 0.3s ease;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .actions .btn-small {
            padding: 6px 8px;
            font-size: 12px;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
            border: 1px solid var(--warning-color);
        }

        .btn-warning:hover {
            background: #e67e22;
            border-color: #e67e22;
        }

        /* Styles pour les lignes selon le statut */
        tr.rupture {
            background: rgba(231, 76, 60, 0.05);
        }

        tr.alerte {
            background: rgba(243, 156, 18, 0.05);
        }

        tr.normal {
            background: rgba(39, 174, 96, 0.05);
        }

        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-form {
            padding: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>

    <script>
        function reapprovisionnerRapide(produitId, produitNom) {
            document.getElementById('modal_produit_id').value = produitId;
            document.getElementById('modal_produit_nom').value = produitNom;
            document.getElementById('reapprovisionModal').style.display = 'flex';
        }

        function closeReapprovisionModal() {
            document.getElementById('reapprovisionModal').style.display = 'none';
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('reapprovisionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReapprovisionModal();
            }
        });

        // Fermer le modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReapprovisionModal();
            }
        });

        // Mise à jour automatique des statuts de stock
        setInterval(function() {
            // On pourrait ici faire un appel AJAX pour vérifier les changements de stock
            // et mettre à jour l'affichage en temps réel
        }, 30000); // Toutes les 30 secondes
    </script>
</body>
</html>product-info">
                                            <strong><?= htmlspecialchars($produit['nom']) ?></strong>
                                            <small><?= htmlspecialchars($produit['reference']) ?></small>
                                            <?php if ($produit['taille'] || $produit['couleur']): ?>
                                                <div class="product-variants">
                                                    <?php if ($produit['taille']): ?>
                                                        <span class="variant-badge"><?= htmlspecialchars($produit['taille']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($produit['couleur']): ?>
                                                        <span class="variant-badge"><?= htmlspecialchars($produit['couleur']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($produit['marque']): ?>
                                                <small style="color: #666;"><?= htmlspecialchars($produit['marque']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($produit['categorie_nom'] ?? 'Non classé') ?></td>
                                    <td>
                                        <div class="