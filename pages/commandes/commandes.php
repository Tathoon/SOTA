<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Seuls Admin et Commercial peuvent accéder à cette page
requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$commandes = $manager->getCommandes();
$user = getCurrentUser();

// Données d'exemple si aucune commande en base
if (empty($commandes)) {
    $commandes = [
        [
            'id' => 1,
            'numero_commande' => 'CMD-2025-001',
            'client_nom' => 'Dupont Pierre',
            'date_commande' => '2025-06-01',
            'statut' => 'en_attente',
            'total' => 150.00,
            'nb_produits' => 3
        ],
        [
            'id' => 2,
            'numero_commande' => 'CMD-2025-002',
            'client_nom' => 'Martin Sophie',
            'date_commande' => '2025-05-30',
            'statut' => 'confirmee',
            'total' => 75.50,
            'nb_produits' => 2
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des commandes - SOTA</title>
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
                <h2><i class="fas fa-shopping-cart"></i> Gestion des commandes</h2>
                <p class="dashboard-subtitle">Suivi et gestion des commandes clients</p>
            </section>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Nb produits</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $commande): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($commande['numero_commande'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($commande['client_nom'] ?? '') ?></td>
                        <td><?= formatDate($commande['date_commande'] ?? date('Y-m-d')) ?></td>
                        <td><?= $commande['nb_produits'] ?? 0 ?></td>
                        <td><?= formatPrice($commande['total'] ?? 0) ?></td>
                        <td><?= getStatusBadge($commande['statut'] ?? 'en_attente') ?></td>
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
                    <i class="fas fa-plus"></i> Nouvelle commande
                </button>
                <button onclick="alert('Fonctionnalité à venir')" class="btn-border">
                    <i class="fas fa-truck"></i> Planning livraisons
                </button>
                <button onclick="alert('Export à venir')" class="btn-border">
                    <i class="fas fa-download"></i> Exporter
                </button>
            </div>
        </main>
    </div>
</body>
</html>
