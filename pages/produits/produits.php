<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Seuls Admin et Gérant peuvent accéder à cette page
requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$produits = $manager->getProduits();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - SOTA</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container stock-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="main-content stock-main">
            <div class="top-bar">
                <a href="../../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h2><i class="fas fa-box-open"></i> Gestion des produits</h2>
                <p class="dashboard-subtitle">Liste des produits disponibles dans l'inventaire</p>
            </section>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th>Stock</th>
                        <th>Prix</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $produit): ?>
                    <tr>
                        <td><?= htmlspecialchars($produit['reference'] ?? '') ?></td>
                        <td><?= htmlspecialchars($produit['nom'] ?? '') ?></td>
                        <td><?= htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé') ?></td>
                        <td><?= $produit['stock_actuel'] ?? 0 ?></td>
                        <td><?= formatPrice($produit['prix_vente'] ?? 0) ?></td>
                        <td>
                            <?php 
                            $stock = $produit['stock_actuel'] ?? 0;
                            $seuil = $produit['seuil_minimum'] ?? 5;
                            if ($stock == 0): ?>
                                <span class="status rupture">Rupture</span>
                            <?php elseif ($stock <= $seuil): ?>
                                <span class="status alerte">Alerte</span>
                            <?php else: ?>
                                <span class="status actif">Actif</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <button class="btn-border btn-small" onclick="alert('Fonctionnalité à venir')">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="stock-actions">
                <button onclick="alert('Fonctionnalité à venir')" class="btn-orange">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
                <button onclick="alert('Fonctionnalité à venir')" class="btn-border">
                    <i class="fas fa-exchange-alt"></i> Mouvement de stock
                </button>
                <button onclick="alert('Export à venir')" class="btn-border">
                    <i class="fas fa-download"></i> Exporter
                </button>
            </div>
        </main>
    </div>
</body>
</html>
