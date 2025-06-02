<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Restriction d'accès : Admin ou Gérant uniquement
requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$categories = $manager->getCategories();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des catégories - SOTA</title>
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
                <h2><i class="fas fa-tags"></i> Gestion des catégories</h2>
                <p class="dashboard-subtitle">Organisez les produits par catégorie pour faciliter la gestion</p>
            </section>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Nom de la catégorie</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $categorie): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($categorie['nom'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($categorie['description'] ?? '-') ?></td>
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
                    <i class="fas fa-plus"></i> Ajouter une catégorie
                </button>
            </div>
        </main>
    </div>
</body>
</html>
