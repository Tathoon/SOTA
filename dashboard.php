<?php
require_once(__DIR__ . '/includes/session.php');
require_once(__DIR__ . '/includes/functions.php');

requireLogin(); // redirige vers login.php si session absente

$manager = new SotaManager();
$user = getCurrentUser();
$role = $user['role'] ?? 'Aucun';

// Statistiques dynamiques adaptées au rôle
$stats = $manager->getStatistiquesDashboard($role);
$alertes = $manager->getProduitsAlerteSeuil();
$notifications = $manager->verifierAlertes();
$commandes_recentes = $manager->getCommandes('', 5);

// Statistiques détaillées pour Admin/Gérant
$stats_detaillees = [];
if (in_array($role, ['Admin', 'Gérant'])) {
    $stats_detaillees = $manager->getStatistiquesDetaillees();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - SOTA Fashion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content dashboard-main">
            <div class="top-bar">
                <div class="user-info">
                    <span>Bonjour, <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></span>
                    <span class="user-role"><?php echo ucfirst($role); ?></span>
                </div>
                <div class="notifications-zone">
                    <?php if (!empty($notifications)): ?>
                        <div class="notification-bell" onclick="afficherNotifications()">
                            <i class="fas fa-bell"></i>
                            <span class="notification-count"><?= count($notifications) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h1><i class="fas fa-chart-line"></i> Tableau de bord</h1>
                <p class="dashboard-subtitle">Vue d'ensemble de votre activité - Prêt-à-porter féminin</p>
            </section>

            <!-- Notifications/Alertes -->
            <?php if (!empty($notifications)): ?>
                <div class="notifications-panel" id="notificationsPanel" style="display: none;">
                    <h3><i class="fas fa-exclamation-triangle"></i> Alertes et notifications</h3>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="alert-item alert-<?= $notif['niveau'] ?>">
                            <span class="alert-message"><?= htmlspecialchars($notif['message']) ?></span>
                            <span class="alert-time"><?= date('H:i', $notif['timestamp']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques principales adaptées au rôle -->
            <div class="stats-grid">
                <?php if (in_array($role, ['Admin', 'Gérant', 'Préparateur'])): ?>
                <div class="stat-card">
                    <i class="fas fa-tshirt icon"></i>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_produits']); ?></h3>
                        <p>Articles mode</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Gérant', 'Préparateur'])): ?>
                <div class="stat-card <?= ($stats['produits_alerte'] + $stats['produits_rupture']) > 0 ? 'alert' : '' ?>">
                    <i class="fas fa-exclamation-triangle icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['produits_alerte'] + $stats['produits_rupture']; ?></h3>
                        <p>Alertes stock</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Commercial', 'Préparateur'])): ?>
                <div class="stat-card">
                    <i class="fas fa-shopping-cart icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['commandes_attente']; ?></h3>
                        <p>Commandes en attente</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
                <div class="stat-card">
                    <i class="fas fa-euro-sign icon"></i>
                    <div class="stat-content">
                        <h3><?= formatPrice($stats['ca_mois']) ?></h3>
                        <p>CA du mois</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Livreur'])): ?>
                <div class="stat-card">
                    <i class="fas fa-truck icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['livraisons_cours']; ?></h3>
                        <p>Livraisons en cours</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($role === 'Admin'): ?>
                <div class="stat-card">
                    <i class="fas fa-users icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['users_actifs'] ?? 0; ?></h3>
                        <p>Utilisateurs actifs</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Alertes stocks critiques -->
            <?php if (!empty($alertes) && in_array($role, ['Admin', 'Gérant', 'Préparateur'])): ?>
            <section class="dashboard-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Alertes de stock</h2>
                <div class="alert-list">
                    <?php foreach (array_slice($alertes, 0, 5) as $produit): ?>
                    <div class="alert-item">
                        <div class="product-info">
                            <span class="product-name"><?php echo htmlspecialchars($produit['nom']); ?></span>
                            <small><?php echo htmlspecialchars($produit['reference']); ?></small>
                        </div>
                        <span class="stock-level"><?php echo $produit['stock_actuel']; ?> en stock</span>
                        <span class="threshold">Seuil: <?php echo $produit['seuil_minimum']; ?></span>
                        <?php if (in_array($role, ['Admin', 'Préparateur'])): ?>
                        <a href="pages/stocks/mouvement.php?produit=<?= $produit['id'] ?>" class="btn-border btn-small">
                            <i class="fas fa-plus"></i> Réapprovisionner
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($alertes) > 5): ?>
                    <a href="pages/stocks/stocks.php?filtre=critique" class="view-all">
                        Voir tous les <?= count($alertes) ?> produits en alerte →
                    </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Raccourcis rapides adaptés au rôle -->
                <section class="dashboard-section">
                    <h2><i class="fas fa-rocket"></i> Accès rapide</h2>
                    <div class="cards">
                        <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
                        <div class="card">
                            <i class="fas fa-plus icon"></i>
                            <p>Nouveau produit</p>
                            <a href="pages/produits/nouveau.php" class="btn-orange">Ajouter</a>
                        </div>
                        <div class="card">
                            <i class="fas fa-truck-loading icon"></i>
                            <p>Nouveau fournisseur</p>
                            <a href="pages/fournisseurs/nouveau.php" class="btn-orange">Ajouter</a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($role, ['Admin', 'Commercial'])): ?>
                        <div class="card">
                            <i class="fas fa-shopping-cart icon"></i>
                            <p>Nouvelle commande</p>
                            <a href="pages/commandes/nouvelle.php" class="btn-orange">Créer</a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($role, ['Admin', 'Préparateur'])): ?>
                        <div class="card">
                            <i class="fas fa-exchange-alt icon"></i>
                            <p>Mouvement stock</p>
                            <a href="pages/stocks/mouvement.php" class="btn-orange">Ajouter</a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($role, ['Admin', 'Livreur'])): ?>
                        <div class="card">
                            <i class="fas fa-truck icon"></i>
                            <p>Planifier livraison</p>
                            <a href="pages/livraisons/nouvelle.php" class="btn-orange">Planifier</a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($role === 'Admin'): ?>
                        <div class="card">
                            <i class="fas fa-sync icon"></i>
                            <p>Sync SAGE</p>
                            <a href="api/sage_integration.php" class="btn-orange">Synchroniser</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Commandes récentes -->
                <?php if (in_array($role, ['Admin', 'Commercial', 'Préparateur'])): ?>
                <section class="dashboard-section">
                    <h2><i class="fas fa-list"></i> Commandes récentes</h2>
                    <div class="recent-list">
                        <?php if (empty($commandes_recentes)): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">
                                <i class="fas fa-shopping-cart" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                                Aucune commande récente
                            </p>
                            <?php if (in_array($role, ['Admin', 'Commercial'])): ?>
                                <div style="text-align: center;">
                                    <a href="pages/commandes/nouvelle.php" class="btn-orange">
                                        <i class="fas fa-plus"></i> Créer la première commande
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php foreach ($commandes_recentes as $commande): ?>
                            <div class="recent-item">
                                <div class="order-info">
                                    <strong><?php echo htmlspecialchars($commande['numero_commande']); ?></strong>
                                    <small><?php echo htmlspecialchars($commande['client_nom']); ?></small>
                                </div>
                                <span class="date"><?php echo formatDate($commande['date_commande']); ?></span>
                                <span class="amount"><?= formatPrice($commande['total']) ?></span>
                                <?php echo getStatusBadge($commande['statut']); ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($commandes_recentes)): ?>
                        <a href="pages/commandes/commandes.php" class="view-all">
                            Voir toutes les commandes →
                        </a>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>

            <!-- Statistiques détaillées pour Admin/Gérant -->
            <?php if (in_array($role, ['Admin', 'Gérant']) && !empty($stats_detaillees)): ?>
            <section class="dashboard-section">
                <h2><i class="fas fa-chart-bar"></i> Statistiques détaillées</h2>
                <div class="stats-detailed-grid">
                    <div class="stat-detailed">
                        <h4>Top catégories</h4>
                        <?php if (!empty($stats_detaillees['top_categories'])): ?>
                            <?php foreach ($stats_detaillees['top_categories'] as $cat): ?>
                                <div class="stat-row">
                                    <span><?= htmlspecialchars($cat['nom']) ?></span>
                                    <span><?= $cat['nb_produits'] ?> produits</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-detailed">
                        <h4>Évolution mensuelle</h4>
                        <div class="stat-row">
                            <span>CA mois précédent</span>
                            <span><?= formatPrice($stats_detaillees['ca_mois_precedent'] ?? 0) ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Évolution</span>
                            <span class="<?= ($stats_detaillees['evolution_ca'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                                <?= ($stats_detaillees['evolution_ca'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($stats_detaillees['evolution_ca'] ?? 0, 1) ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Actions rapides supplémentaires -->
            <section class="dashboard-section">
                <h2><i class="fas fa-tools"></i> Actions rapides</h2>
                <div class="stock-actions">
                    <?php if (in_array($role, ['Admin', 'Préparateur'])): ?>
                        <a href="pages/stocks/stocks.php" class="btn-border">
                            <i class="fas fa-warehouse"></i> Voir les stocks
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
                        <a href="pages/produits/produits.php" class="btn-border">
                            <i class="fas fa-tshirt"></i> Tous les produits
                        </a>
                        <a href="pages/fournisseurs/fournisseurs.php" class="btn-border">
                            <i class="fas fa-truck-loading"></i> Fournisseurs
                        </a>
                        <a href="pages/categories/categories.php" class="btn-border">
                            <i class="fas fa-tags"></i> Catégories
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($role, ['Admin', 'Commercial'])): ?>
                        <a href="pages/commandes/commandes.php" class="btn-border">
                            <i class="fas fa-shopping-cart"></i> Commandes
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($role, ['Admin', 'Livreur'])): ?>
                        <a href="pages/livraisons/livraisons.php" class="btn-border">
                            <i class="fas fa-truck"></i> Livraisons
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($role === 'Admin'): ?>
                        <a href="pages/utilisateurs/utilisateurs.php" class="btn-border">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a>
                        <a href="pages/rapports/rapports.php" class="btn-border">
                            <i class="fas fa-chart-pie"></i> Rapports
                        </a>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <style>
        .notifications-zone {
            position: relative;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 10px;
            color: #ff6b35;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            color: #e55a2b;
        }

        .notification-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            min-width: 18px;
            text-align: center;
        }

        .notifications-panel {
            background: white;
            margin: 0 30px 20px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #ff6b35;
        }

        .alert-info { border-left-color: #3498db; }
        .alert-warning { border-left-color: #f39c12; }
        .alert-error { border-left-color: #e74c3c; }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .product-info small {
            color: #666;
            font-size: 12px;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .order-info small {
            color: #666;
            font-size: 12px;
        }

        .amount {
            font-weight: 600;
            color: #27ae60;
        }

        .stats-detailed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .stat-detailed {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }

        .stat-detailed h4 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 16px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .positive {
            color: #27ae60;
            font-weight: 600;
        }

        .negative {
            color: #e74c3c;
            font-weight: 600;
        }

        .alert-time {
            font-size: 12px;
            color: #666;
        }
    </style>

    <script>
        function afficherNotifications() {
            const panel = document.getElementById('notificationsPanel');
            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Mise à jour automatique des données
        setInterval(function() {
            if (!document.hidden) {
                fetch('api/dashboard_update.php')
                    .then(response => response.json())
                    .then(data => {
                        // Mise à jour des statistiques en temps réel
                        if (data.notifications && data.notifications.length > 0) {
                            const bell = document.querySelector('.notification-count');
                            if (bell) {
                                bell.textContent = data.notifications.length;
                            }
                        }
                    })
                    .catch(error => console.log('Mise à jour silencieuse échouée'));
            }
        }, 60000); // Toutes les minutes
    </script>
</body>
</html>