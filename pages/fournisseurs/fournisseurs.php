<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Seuls Admin et Gérant peuvent accéder à cette page
requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$fournisseurs = $manager->getFournisseurs();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des fournisseurs - SOTA</title>
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
                <h2><i class="fas fa-industry"></i> Gestion des fournisseurs</h2>
                <p class="dashboard-subtitle">Liste des fournisseurs et leurs informations</p>
            </section>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Ville</th>
                        <th>Téléphone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fournisseurs as $fournisseur): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($fournisseur['nom'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($fournisseur['ville'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($fournisseur['telephone'] ?? '-') ?></td>
                        <td class="actions">
                            <button class="btn-border btn-small" onclick="alert('Fonctionnalité à venir')">
                                <i class="fas fa-eye"></i> Voir
                            </button>
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
                    <i class="fas fa-plus"></i> Ajouter fournisseur
                </button>
                <button onclick="alert('Fonctionnalité à venir')" class="btn-border">
                    <i class="fas fa-history"></i> Historique des commandes
                </button>
            </div>
        </main>
    </div>
</body>
</html>
