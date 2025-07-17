<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$statut_stock = $_GET['statut_stock'] ?? '';

// Récupération des données
$produits = $manager->getProduits($search, $category, $statut_stock);
$categories = $manager->getCategories();

// Message de succès/erreur
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
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
                <h1><i class="fas fa-tshirt"></i> Gestion des lots</h1>
                <p class="dashboard-subtitle">Catalogue prêt-à-porter féminin - <?= count($produits) ?> articles</p>
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

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher un lot (nom, référence, marque...)" 
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

                    <select name="statut_stock" class="filter-select">
                        <option value="">Tous les stocks</option>
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

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="nouveau.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouveau lot
                    </a>
                    <a href="export.php?format=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . $category : '' ?><?= $statut_stock ? '&statut_stock=' . $statut_stock : '' ?>" 
                       class="btn-border">
                        <i class="fas fa-download"></i> Exporter CSV
                    </a>
                </div>
            </section>

            <!-- Liste des produits -->
            <div class="stock-table-container">
                <?php if (empty($produits)): ?>
                    <div style="text-align: center; padding: 50px; background: white; margin: 0 30px; border-radius: 12px;">
                        <i class="fas fa-tshirt" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                        <h3 style="color: #666; margin-bottom: 10px;">Aucun lot trouvé</h3>
                        <p style="color: #999; margin-bottom: 20px;">
                            <?= $search || $category || $statut_stock ? 'Aucun lot ne correspond à vos critères de recherche.' : 'Votre catalogue est vide.' ?>
                        </p>
                        <?php if (!$search && !$category && !$statut_stock): ?>
                            <a href="nouveau.php" class="btn-orange">
                                <i class="fas fa-plus"></i> Ajouter le premier lot
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Lot</th>
                                <th>Catégorie</th>
                                <th>Stock</th>
                                <th>Prix</th>
                                <th>Marge</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits as $produit): ?>
                                <tr>
                                    <td>
                                        <div class="product-image">
                                            <i class="fas fa-tshirt" style="font-size: 20px; color: #ff6b35;"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="product-info">
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
                                        <div class="stock-info">
                                            <strong><?= $produit['stock_actuel'] ?></strong>
                                            <small>Seuil: <?= $produit['seuil_minimum'] ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-info">
                                            <strong><?= formatPrice($produit['prix_vente']) ?></strong>
                                            <?php if ($produit['prix_achat']): ?>
                                                <small>Achat: <?= formatPrice($produit['prix_achat']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($produit['prix_achat'] && $produit['prix_achat'] > 0): ?>
                                            <?php $marge_pct = (($produit['prix_vente'] - $produit['prix_achat']) / $produit['prix_achat']) * 100; ?>
                                            <span class="marge-badge <?= $marge_pct >= 50 ? 'positive' : ($marge_pct >= 20 ? 'moderate' : 'low') ?>">
                                                +<?= number_format($marge_pct, 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getStatusBadge($produit['statut_stock']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="details.php?id=<?= $produit['id'] ?>" class="btn-border btn-small" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="modifier.php?id=<?= $produit['id'] ?>" class="btn-border btn-small" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../stocks/mouvement.php?produit=<?= $produit['id'] ?>" class="btn-border btn-small" title="Gérer stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
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

    <style>
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

        .stock-info, .price-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .stock-info small, .price-info small {
            color: #666;
            font-size: 11px;
        }

        .marge-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .marge-badge.positive {
            background: #d4edda;
            color: #155724;
        }

        .marge-badge.moderate {
            background: #fff3cd;
            color: #856404;
        }

        .marge-badge.low {
            background: #f8d7da;
            color: #721c24;
        }

        .product-image {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dee2e6;
        }

        .stock-table-container {
            margin: 0 30px;
        }

        .stock-table th {
            background: var(--secondary-color);
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }

        .stock-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .actions .btn-small {
            padding: 6px 8px;
            font-size: 12px;
        }
    </style>
</body>
</html>