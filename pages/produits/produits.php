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
                <h1><i class="fas fa-boxes"></i> Gestion des lots</h1>
                <p class="dashboard-subtitle">
                    Gérez vos lots de vêtements avec leurs différentes tailles et variantes - <?= count($produits) ?> lots disponibles
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

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="nouveau.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouveau lot
                    </a>
                    <a href="../categories/categories.php" class="btn-border">
                        <i class="fas fa-tags"></i> Gérer les catégories
                    </a>
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
            <div class="stock-table-container">
                <?php if (empty($produits)): ?>
                    <div style="text-align: center; padding: 50px; background: white; margin: 0 30px; border-radius: 12px;">
                        <i class="fas fa-boxes" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                        <h3 style="color: #666; margin-bottom: 10px;">Aucun lot trouvé</h3>
                        <p style="color: #999; margin-bottom: 20px;">
                            <?= $search || $category || $statut_stock ? 'Aucun lot ne correspond à vos critères de recherche.' : 'Votre catalogue de lots est vide.' ?>
                        </p>
                        <?php if (!$search && !$category && !$statut_stock): ?>
                            <a href="nouveau.php" class="btn-orange">
                                <i class="fas fa-plus"></i> Créer le premier lot
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
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
                            <?php foreach ($produits as $produit): ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <strong style="color: #ff6b35;"><?= htmlspecialchars($produit['reference']) ?></strong>
                                            <?php if ($produit['marque']): ?>
                                                <br><small style="color: #666;"><?= htmlspecialchars($produit['marque']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="product-info">
                                            <strong><?= htmlspecialchars($produit['nom']) ?></strong>
                                            <?php if ($produit['couleur']): ?>
                                                <br><span style="color: #666; font-size: 0.9em;">
                                                    <i class="fas fa-palette"></i> <?= htmlspecialchars($produit['couleur']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($produit['composition']): ?>
                                                <br><small style="color: #888;"><?= htmlspecialchars($produit['composition']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status actif">
                                            <?= htmlspecialchars($produit['categorie_nom'] ?? 'Non classé') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="size-indicators">
                                            <?php if ($produit['taille']): ?>
                                                <?php 
                                                // Simulation des tailles disponibles (en réalité, il faudrait une table de variantes)
                                                $tailles_courantes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                                $taille_principale = htmlspecialchars($produit['taille']);
                                                ?>
                                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                    <?php foreach ($tailles_courantes as $taille): ?>
                                                        <span class="size-badge <?= $taille === $taille_principale ? 'active' : '' ?>" 
                                                              style="padding: 2px 6px; background: <?= $taille === $taille_principale ? '#ff6b35' : '#f0f0f0' ?>; 
                                                                     color: <?= $taille === $taille_principale ? 'white' : '#666' ?>; 
                                                                     border-radius: 3px; font-size: 0.8em; font-weight: 500;">
                                                            <?= $taille ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <small style="color: #666; margin-top: 4px; display: block;">
                                                    <i class="fas fa-star" style="color: #ff6b35;"></i> Taille principale: <?= $taille_principale ?>
                                                </small>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">Taille unique</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($produit['lot_minimum'] > 1): ?>
                                                <br><small style="color: #666;">
                                                    <i class="fas fa-box"></i> Lot minimum: <?= $produit['lot_minimum'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="stock-info">
                                            <span class="stock-quantity <?= $produit['statut_stock'] ?>" 
                                                  style="font-weight: bold; font-size: 1.1em;">
                                                <?= $produit['stock_actuel'] ?>
                                            </span>
                                            <span style="color: #666;"> unités</span>
                                            <br><small style="color: #666;">Seuil: <?= $produit['seuil_minimum'] ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-info">
                                            <strong style="color: #ff6b35; font-size: 1.1em;">
                                                <?= number_format($produit['prix_vente'], 2) ?>€
                                            </strong>
                                            <br><small style="color: #666;">par unité</small>
                                            <?php if ($produit['prix_achat']): ?>
                                                <br><small style="color: #888;">
                                                    Achat: <?= number_format($produit['prix_achat'], 2) ?>€
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'normal' => 'actif',
                                            'alerte' => 'alerte', 
                                            'rupture' => 'rupture'
                                        ];
                                        $status_labels = [
                                            'normal' => 'Stock OK',
                                            'alerte' => 'Stock bas',
                                            'rupture' => 'Rupture'
                                        ];
                                        ?>
                                        <span class="status <?= $status_colors[$produit['statut_stock']] ?>">
                                            <?= $status_labels[$produit['statut_stock']] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="modifier.php?id=<?= $produit['id'] ?>" 
                                               class="btn-border btn-small" 
                                               title="Modifier le lot">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../stocks/mouvement.php?produit=<?= $produit['id'] ?>" 
                                               class="btn-border btn-small" 
                                               title="Ajuster le stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            <a href="../stocks/historique.php?produit=<?= $produit['id'] ?>" 
                                               class="btn-border btn-small" 
                                               title="Voir l'historique">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Légende des tailles -->
            <div class="dashboard-section" style="margin: 20px 30px; background: #f8f9fa;">
                <h3 style="margin: 0 0 15px 0; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-ruler"></i> Guide des tailles
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.9em;">
                    <div>
                        <strong>XS :</strong> Extra Small (32-34)
                    </div>
                    <div>
                        <strong>S :</strong> Small (36-38)
                    </div>
                    <div>
                        <strong>M :</strong> Medium (40-42)
                    </div>
                    <div>
                        <strong>L :</strong> Large (44-46)
                    </div>
                    <div>
                        <strong>XL :</strong> Extra Large (48-50)
                    </div>
                    <div>
                        <strong>XXL :</strong> Double XL (52-54)
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>