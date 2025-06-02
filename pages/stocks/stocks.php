<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Préparateur']); // ← Ajout de la restriction de rôle

$manager = new SotaManager();
$produits = $manager->getProduits();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des stocks - SOTA</title>
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
                <h2><i class="fas fa-warehouse"></i> Gestion des stocks</h2>
                <p class="dashboard-subtitle">Consultez l'état des produits disponibles</p>
            </section>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th>Stock actuel</th>
                        <th>Seuil minimum</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $produit): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($produit['reference'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($produit['nom'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé'); ?></td>
                        <td><strong><?php echo $produit['stock_actuel'] ?? 0; ?></strong></td>
                        <td><?php echo $produit['seuil_minimum'] ?? 5; ?></td>
                        <td>
                            <?php 
                            $stock = $produit['stock_actuel'] ?? 0;
                            $seuil = $produit['seuil_minimum'] ?? 5;
                            if ($stock == 0): ?>
                                <span class="status rupture">Rupture</span>
                            <?php elseif ($stock <= $seuil): ?>
                                <span class="status alerte">Alerte</span>
                            <?php else: ?>
                                <span class="status actif">Normal</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <button class="btn-border btn-small" onclick="alert('Fonctionnalité à venir')">
                                <i class="fas fa-exchange-alt"></i> Mouvement
                            </button>
                            <button class="btn-border btn-small" onclick="alert('Fonctionnalité à venir')">
                                <i class="fas fa-history"></i> Historique
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="stock-actions">
                <button onclick="alert('Fonctionnalité à venir')" class="btn-orange">
                    <i class="fas fa-exchange-alt"></i> Nouveau mouvement
                </button>
                <button onclick="alert('Fonctionnalité à venir')" class="btn-border">
                    <i class="fas fa-history"></i> Historique complet
                </button>
                <button onclick="alert('Export à venir')" class="btn-border">
                    <i class="fas fa-download"></i> Exporter stocks
                </button>
            </div>
        </main>
    </div>
</body>
</html>
