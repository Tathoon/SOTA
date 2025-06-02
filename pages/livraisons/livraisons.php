<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Seuls Admin et Livreur peuvent accéder à cette page
requireLogin(['Admin', 'Livreur']);

$user = getCurrentUser();

// Données d'exemple pour les livraisons
$livraisons = [
    ['date' => '05/06/2025', 'commande' => 'CMD-001', 'statut' => 'Planifiée'],
    ['date' => '06/06/2025', 'commande' => 'CMD-002', 'statut' => 'En cours'],
    ['date' => '07/06/2025', 'commande' => 'CMD-003', 'statut' => 'Livrée']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des livraisons - SOTA</title>
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
                <h2><i class="fas fa-truck"></i> Planning des livraisons</h2>
                <p class="dashboard-subtitle">Suivi des livraisons et expéditions</p>
            </section>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Commande</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($livraisons as $livraison): ?>
                    <tr>
                        <td><?= htmlspecialchars($livraison['date']) ?></td>
                        <td><strong><?= htmlspecialchars($livraison['commande']) ?></strong></td>
                        <td>
                            <span class="status <?= strtolower(str_replace(' ', '-', $livraison['statut'])) ?>">
                                <?= htmlspecialchars($livraison['statut']) ?>
                            </span>
                        </td>
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
                    <i class="fas fa-plus"></i> Planifier livraison
                </button>
                <button onclick="alert('Fonctionnalité à venir')" class="btn-border">
                    <i class="fas fa-search"></i> Suivi des statuts
                </button>
            </div>
        </main>
    </div>
</body>
</html>
