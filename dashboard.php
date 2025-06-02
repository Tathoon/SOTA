<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin(); // redirige vers login.php si session absente

$manager = new SotaManager();
$stats = $manager->getStatistiquesDashboard();
$alertes = $manager->getProduitsAlerteSeuil();
$commandes_recentes = $manager->getCommandes('', 5);

$user = getCurrentUser(); // array ['prenom', 'nom', 'role']
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - SOTA</title>
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
                    <span class="user-role">(<?php echo ucfirst($user['role']); ?>)</span>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h1><i class="fas fa-chart-line"></i> Tableau de bord</h1>
                <p class="dashboard-subtitle">Vue d'ensemble de votre activité</p>
            </section>

            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-box icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_produits']; ?></h3>
                        <p>Produits actifs</p>
                    </div>
                </div>
                <div class="stat-card alert">
                    <i class="fas fa-exclamation-triangle icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['produits_alerte'] + $stats['produits_rupture']; ?></h3>
                        <p>Alertes stock</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shopping-cart icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['commandes_attente']; ?></h3>
                        <p>Commandes en attente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-truck icon"></i>
                    <div class="stat-content">
                        <h3><?php echo $stats['livraisons_cours']; ?></h3>
                        <p>Livraisons en cours</p>
                    </div>
                </div>
            </div>

            <!-- Alertes stocks -->
            <?php if (!empty($alertes)): ?>
            <section class="dashboard-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Alertes de stock</h2>
                <div class="alert-list">
                    <?php foreach ($alertes as $produit): ?>
                    <div class="alert-item">
                        <span class="product-name"><?php echo htmlspecialchars($produit['nom']); ?></span>
                        <span class="stock-level"><?php echo $produit['stock_actuel']; ?> en stock</span>
                        <span class="threshold">Seuil: <?php echo $produit['seuil_minimum']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Raccourcis rapides -->
                <section class="dashboard-section">
                    <h2><i class="fas fa-rocket"></i> Accès rapide</h2>
                    <div class="cards">
                        <div class="card">
                            <i class="fas fa-plus icon"></i>
                            <p>Nouveau produit</p>
                            <a href="pages/produits/produits.php" class="btn-orange">Ajouter</a>
                        </div>
                        <div class="card">
                            <i class="fas fa-shopping-cart icon"></i>
                            <p>Nouvelle commande</p>
                            <a href="pages/commandes/commandes.php" class="btn-orange">Créer</a>
                        </div>
                        <div class="card">
                            <i class="fas fa-exchange-alt icon"></i>
                            <p>Mouvement stock</p>
                            <a href="pages/stocks/stocks.php" class="btn-orange">Ajouter</a>
                        </div>
                    </div>
                </section>

                <!-- Commandes récentes -->
                <section class="dashboard-section">
                    <h2><i class="fas fa-list"></i> Commandes récentes</h2>
                    <div class="recent-list">
                        <?php if (empty($commandes_recentes)): ?>
                            <p>Aucune commande récente</p>
                        <?php else: ?>
                            <?php foreach ($commandes_recentes as $commande): ?>
                            <div class="recent-item">
                                <strong><?php echo htmlspecialchars($commande['numero_commande']); ?></strong>
                                <span><?php echo htmlspecialchars($commande['client_nom']); ?></span>
                                <span class="date"><?php echo formatDate($commande['date_commande']); ?></span>
                                <?php echo getStatusBadge($commande['statut']); ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="pages/commandes/commandes.php" class="view-all">Voir toutes les commandes →</a>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
