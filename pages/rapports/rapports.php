<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

// Période par défaut : mois en cours
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');

// Statistiques générales
try {
    // CA par période
    $stmt = $manager->db->prepare("
        SELECT COALESCE(SUM(total), 0) as ca_periode,
               COUNT(*) as nb_commandes
        FROM commandes 
        WHERE date_commande BETWEEN ? AND ? 
        AND statut = 'livree'
    ");
    $stmt->execute([$date_debut, $date_fin]);
    $stats_ca = $stmt->fetch();

    // Évolution vs période précédente
    $duree_jours = (strtotime($date_fin) - strtotime($date_debut)) / (24 * 3600);
    $date_debut_prec = date('Y-m-d', strtotime($date_debut . " -$duree_jours days"));
    $date_fin_prec = date('Y-m-d', strtotime($date_debut . " -1 day"));
    
    $stmt = $manager->db->prepare("
        SELECT COALESCE(SUM(total), 0) as ca_precedent 
        FROM commandes 
        WHERE date_commande BETWEEN ? AND ? 
        AND statut = 'livree'
    ");
    $stmt->execute([$date_debut_prec, $date_fin_prec]);
    $ca_precedent = $stmt->fetch()['ca_precedent'];
    
    $evolution_ca = $ca_precedent > 0 ? (($stats_ca['ca_periode'] - $ca_precedent) / $ca_precedent) * 100 : 0;

    // Top produits vendus
    $stmt = $manager->db->prepare("
        SELECT p.nom, p.reference, SUM(dc.quantite) as quantite_vendue,
               SUM(dc.sous_total) as ca_produit
        FROM details_commandes dc
        JOIN produits p ON dc.produit_id = p.id
        JOIN commandes c ON dc.commande_id = c.id
        WHERE c.date_commande BETWEEN ? AND ? AND c.statut = 'livree'
        GROUP BY p.id
        ORDER BY quantite_vendue DESC
        LIMIT 10
    ");
    $stmt->execute([$date_debut, $date_fin]);
    $top_produits = $stmt->fetchAll();

    // Top catégories
    $stmt = $manager->db->prepare("
        SELECT cat.nom, COUNT(DISTINCT p.id) as nb_produits,
               SUM(dc.quantite) as quantite_vendue,
               SUM(dc.sous_total) as ca_categorie
        FROM details_commandes dc
        JOIN produits p ON dc.produit_id = p.id
        JOIN categories cat ON p.categorie_id = cat.id
        JOIN commandes c ON dc.commande_id = c.id
        WHERE c.date_commande BETWEEN ? AND ? AND c.statut = 'livree'
        GROUP BY cat.id
        ORDER BY ca_categorie DESC
    ");
    $stmt->execute([$date_debut, $date_fin]);
    $top_categories = $stmt->fetchAll();

    // Statistiques de stock
    $stmt = $manager->db->prepare("
        SELECT 
            COUNT(*) as total_produits,
            SUM(CASE WHEN stock_actuel = 0 THEN 1 ELSE 0 END) as ruptures,
            SUM(CASE WHEN stock_actuel <= seuil_minimum AND stock_actuel > 0 THEN 1 ELSE 0 END) as alertes,
            SUM(stock_actuel * COALESCE(prix_achat, 0)) as valeur_stock
        FROM produits WHERE actif = 1
    ");
    $stmt->execute();
    $stats_stock = $stmt->fetch();

    // Évolution mensuelle CA (12 derniers mois)
    $stmt = $manager->db->prepare("
        SELECT DATE_FORMAT(date_commande, '%Y-%m') as mois,
               SUM(total) as ca_mois,
               COUNT(*) as nb_commandes_mois
        FROM commandes 
        WHERE date_commande >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        AND statut = 'livree'
        GROUP BY DATE_FORMAT(date_commande, '%Y-%m')
        ORDER BY mois
    ");
    $stmt->execute();
    $evolution_mensuelle = $stmt->fetchAll();

} catch (Exception $e) {
    $stats_ca = ['ca_periode' => 0, 'nb_commandes' => 0];
    $evolution_ca = 0;
    $top_produits = [];
    $top_categories = [];
    $stats_stock = ['total_produits' => 0, 'ruptures' => 0, 'alertes' => 0, 'valeur_stock' => 0];
    $evolution_mensuelle = [];
    $error = "Erreur lors du chargement des statistiques";
}

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et statistiques - SOTA Fashion</title>
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
                <h1><i class="fas fa-chart-pie"></i> Rapports et statistiques</h1>
                <p class="dashboard-subtitle">Analyse des performances - Du <?= formatDate($date_debut) ?> au <?= formatDate($date_fin) ?></p>
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

            <!-- Filtres de période -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="form-group">
                        <label for="date_debut">Date début :</label>
                        <input type="date" name="date_debut" id="date_debut" class="form-control" value="<?= $date_debut ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_fin">Date fin :</label>
                        <input type="date" name="date_fin" id="date_fin" class="form-control" value="<?= $date_fin ?>">
                    </div>
                    
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-filter"></i> Actualiser
                    </button>
                    
                    <button type="button" onclick="setQuickPeriod('month')" class="btn-border">Mois en cours</button>
                    <button type="button" onclick="setQuickPeriod('year')" class="btn-border">Année en cours</button>
                </form>
            </div>

            <!-- KPI principaux -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-euro-sign icon"></i>
                    <div class="stat-content">
                        <h3><?= formatPrice($stats_ca['ca_periode']) ?></h3>
                        <p>Chiffre d'affaires</p>
                        <small class="<?= $evolution_ca >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $evolution_ca >= 0 ? '+' : '' ?><?= number_format($evolution_ca, 1) ?>% vs période précédente
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-shopping-cart icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats_ca['nb_commandes'] ?></h3>
                        <p>Commandes livrées</p>
                        <small>
                            Panier moyen: <?= $stats_ca['nb_commandes'] > 0 ? formatPrice($stats_ca['ca_periode'] / $stats_ca['nb_commandes']) : '0 €' ?>
                        </small>
                    </div>
                </div>

                <div class="stat-card">
                    <i class="fas fa-warehouse icon"></i>
                    <div class="stat-content">
                        <h3><?= formatPrice($stats_stock['valeur_stock']) ?></h3>
                        <p>Valeur du stock</p>
                        <small><?= $stats_stock['total_produits'] ?> produits actifs</small>
                    </div>
                </div>

                <div class="stat-card <?= ($stats_stock['ruptures'] + $stats_stock['alertes']) > 0 ? 'alert' : '' ?>">
                    <i class="fas fa-exclamation-triangle icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats_stock['ruptures'] + $stats_stock['alertes'] ?></h3>
                        <p>Alertes stock</p>
                        <small><?= $stats_stock['ruptures'] ?> ruptures, <?= $stats_stock['alertes'] ?> alertes</small>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Top produits -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-medal"></i> Top produits vendus</h2>
                    
                    <?php if (!empty($top_produits)): ?>
                        <div class="recent-list">
                            <?php foreach ($top_produits as $index => $produit): ?>
                            <div class="recent-item">
                                <div>
                                    <span class="text-warning">#<?= $index + 1 ?></span>
                                    <strong><?= htmlspecialchars($produit['nom']) ?></strong>
                                    <small><?= htmlspecialchars($produit['reference']) ?></small>
                                </div>
                                <div>
                                    <strong><?= $produit['quantite_vendue'] ?> vendus</strong>
                                </div>
                                <div>
                                    <?= formatPrice($produit['ca_produit']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">Aucune vente sur la période</p>
                    <?php endif; ?>
                </div>

                <!-- Top catégories -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-tags"></i> Performance par catégorie</h2>
                    
                    <?php if (!empty($top_categories)): ?>
                        <div class="recent-list">
                            <?php foreach ($top_categories as $categorie): ?>
                            <div class="recent-item">
                                <div>
                                    <strong><?= htmlspecialchars($categorie['nom']) ?></strong>
                                    <small><?= $categorie['nb_produits'] ?> produits</small>
                                </div>
                                <div>
                                    <strong><?= $categorie['quantite_vendue'] ?> vendus</strong>
                                </div>
                                <div>
                                    <?= formatPrice($categorie['ca_categorie']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">Aucune vente par catégorie</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Évolution mensuelle -->
            <?php if (!empty($evolution_mensuelle)): ?>
            <div class="dashboard-section">
                <h2><i class="fas fa-chart-line"></i> Évolution mensuelle (12 derniers mois)</h2>
                
                <div class="chart-container">
                    <canvas id="evolutionChart" width="400" height="200"></canvas>
                </div>
                
                <div class="stats-grid">
                    <?php foreach (array_slice($evolution_mensuelle, -4) as $mois): ?>
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3><?= formatPrice($mois['ca_mois']) ?></h3>
                            <p><?= date('M Y', strtotime($mois['mois'] . '-01')) ?></p>
                            <small><?= $mois['nb_commandes_mois'] ?> commandes</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions d'export -->
            <div class="stock-actions">
                <a href="export.php?type=ca&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn-orange">
                    <i class="fas fa-download"></i> Exporter rapport CA
                </a>
                <a href="export.php?type=produits&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn-border">
                    <i class="fas fa-download"></i> Exporter top lot
                </a>
                <a href="export.php?type=stock" class="btn-border">
                    <i class="fas fa-download"></i> Exporter état stock
                </a>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function setQuickPeriod(period) {
            const now = new Date();
            let dateDebut, dateFin;
            
            if (period === 'month') {
                dateDebut = new Date(now.getFullYear(), now.getMonth(), 1);
                dateFin = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            } else if (period === 'year') {
                dateDebut = new Date(now.getFullYear(), 0, 1);
                dateFin = new Date(now.getFullYear(), 11, 31);
            }
            
            document.getElementById('date_debut').value = dateDebut.toISOString().split('T')[0];
            document.getElementById('date_fin').value = dateFin.toISOString().split('T')[0];
        }

        // Graphique évolution mensuelle
        <?php if (!empty($evolution_mensuelle)): ?>
        const ctx = document.getElementById('evolutionChart').getContext('2d');
        const evolutionChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($m) { 
                    return date('M Y', strtotime($m['mois'] . '-01')); 
                }, $evolution_mensuelle)) ?>,
                datasets: [{
                    label: 'Chiffre d\'affaires',
                    data: <?= json_encode(array_column($evolution_mensuelle, 'ca_mois')) ?>,
                    borderColor: '#ff6b35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', {
                                    style: 'currency',
                                    currency: 'EUR'
                                }).format(value);
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
    