<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$produit_id = !empty($_GET['produit']) ? (int)$_GET['produit'] : null;
$type_mouvement = $_GET['type'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$limit = min((int)($_GET['limit'] ?? 50), 200); // Maximum 200 résultats

// Construction de la requête avec filtres
try {
    $sql = "
        SELECT ms.*, p.nom as produit_nom, p.reference as produit_reference,
               u.prenom, u.nom as user_nom, c.nom as categorie_nom
        FROM mouvements_stock ms
        LEFT JOIN produits p ON ms.produit_id = p.id
        LEFT JOIN utilisateurs_ldap u ON ms.utilisateur_id = u.id
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($produit_id) {
        $sql .= " AND ms.produit_id = ?";
        $params[] = $produit_id;
    }

    if ($type_mouvement) {
        $sql .= " AND ms.type_mouvement = ?";
        $params[] = $type_mouvement;
    }

    if ($date_debut) {
        $sql .= " AND DATE(ms.date_mouvement) >= ?";
        $params[] = $date_debut;
    }

    if ($date_fin) {
        $sql .= " AND DATE(ms.date_mouvement) <= ?";
        $params[] = $date_fin;
    }

    $sql .= " ORDER BY ms.date_mouvement DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $manager->db->prepare($sql);
    $stmt->execute($params);
    $mouvements = $stmt->fetchAll();

} catch (Exception $e) {
    $mouvements = [];
    $error = "Erreur lors du chargement de l'historique";
}

// Récupération des produits pour le filtre
$produits = $manager->getProduits();

// Produit sélectionné pour affichage
$produit_selectionne = null;
if ($produit_id) {
    $produit_selectionne = $manager->getProduitById($produit_id);
}

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des stocks - SOTA Fashion</title>
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
                <h1><i class="fas fa-history"></i> Historique des stocks</h1>
                <p class="dashboard-subtitle">
                    Suivi des mouvements de stock - <?= count($mouvements) ?> mouvements
                    <?= $produit_selectionne ? ' pour ' . htmlspecialchars($produit_selectionne['nom']) : '' ?>
                </p>
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

            <!-- Informations du produit sélectionné -->
            <?php if ($produit_selectionne): ?>
                <div class="product-header-card">
                    <div class="product-header-info">
                        <div class="product-main-info">
                            <h3><?= htmlspecialchars($produit_selectionne['nom']) ?></h3>
                            <p><?= htmlspecialchars($produit_selectionne['reference']) ?></p>
                            <?= getStatusBadge($produit_selectionne['statut_stock']) ?>
                        </div>
                        <div class="product-stock-info">
                            <div class="stock-item">
                                <label>Stock actuel</label>
                                <span class="stock-value <?= $produit_selectionne['statut_stock'] ?>">
                                    <?= $produit_selectionne['stock_actuel'] ?>
                                </span>
                            </div>
                            <div class="stock-item">
                                <label>Seuil minimum</label>
                                <span class="stock-value"><?= $produit_selectionne['seuil_minimum'] ?></span>
                            </div>
                            <div class="stock-item">
                                <label>Emplacement</label>
                                <span class="stock-value"><?= $produit_selectionne['emplacement'] ?: 'Non défini' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="product-actions">
                        <a href="mouvement.php?produit=<?= $produit_selectionne['id'] ?>" class="btn-orange">
                            <i class="fas fa-exchange-alt"></i> Nouveau mouvement
                        </a>
                        <a href="../produits/details.php?id=<?= $produit_selectionne['id'] ?>" class="btn-border">
                            <i class="fas fa-eye"></i> Voir produit
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-row">
                        <select name="produit" class="filter-select">
                            <option value="">Tous les produits</option>
                            <?php foreach ($produits as $produit): ?>
                                <option value="<?= $produit['id'] ?>" <?= $produit_id == $produit['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($produit['nom']) ?> (<?= htmlspecialchars($produit['reference']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="type" class="filter-select">
                            <option value="">Tous les types</option>
                            <option value="entree" <?= $type_mouvement === 'entree' ? 'selected' : '' ?>>Entrées</option>
                            <option value="sortie" <?= $type_mouvement === 'sortie' ? 'selected' : '' ?>>Sorties</option>
                            <option value="ajustement" <?= $type_mouvement === 'ajustement' ? 'selected' : '' ?>>Ajustements</option>
                        </select>
                        
                        <input type="date" name="date_debut" class="filter-select" 
                               value="<?= htmlspecialchars($date_debut) ?>" placeholder="Date début">
                        
                        <input type="date" name="date_fin" class="filter-select" 
                               value="<?= htmlspecialchars($date_fin) ?>" placeholder="Date fin">
                        
                        <select name="limit" class="filter-select">
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 résultats</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 résultats</option>
                            <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200 résultats</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-orange">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        
                        <a href="historique.php" class="btn-border">
                            <i class="fas fa-times"></i> Effacer
                        </a>
                    </div>
                </form>
            </section>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="mouvement.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouveau mouvement
                    </a>
                    <a href="stocks.php" class="btn-border">
                        <i class="fas fa-warehouse"></i> Retour aux stocks
                    </a>
                </div>
            </section>

            <!-- Liste des mouvements -->
            <div class="history-container">
                <?php if (empty($mouvements)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>Aucun mouvement trouvé</h3>
                        <p>
                            <?= ($produit_id || $type_mouvement || $date_debut || $date_fin) ? 
                                'Aucun mouvement ne correspond à vos critères.' : 
                                'Aucun mouvement de stock enregistré.' ?>
                        </p>
                        <a href="mouvement.php" class="btn-orange">
                            <i class="fas fa-plus"></i> Enregistrer le premier mouvement
                        </a>
                    </div>
                <?php else: ?>
                    <div class="movements-timeline">
                        <?php 
                        $current_date = '';
                        foreach ($mouvements as $mouvement): 
                            $movement_date = date('Y-m-d', strtotime($mouvement['date_mouvement']));
                            
                            // Afficher la date si elle change
                            if ($current_date !== $movement_date):
                                $current_date = $movement_date;
                        ?>
                                <div class="date-separator">
                                    <span class="date-label"><?= formatDate($movement_date) ?></span>
                                </div>
                        <?php endif; ?>
                        
                            <div class="movement-timeline-item <?= $mouvement['type_mouvement'] ?>">
                                <div class="movement-icon">
                                    <?php if ($mouvement['type_mouvement'] === 'entree'): ?>
                                        <i class="fas fa-plus-circle"></i>
                                    <?php elseif ($mouvement['type_mouvement'] === 'sortie'): ?>
                                        <i class="fas fa-minus-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-balance-scale"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="movement-content">
                                    <div class="movement-header">
                                        <div class="movement-product">
                                            <strong><?= htmlspecialchars($mouvement['produit_nom']) ?></strong>
                                            <span class="product-ref"><?= htmlspecialchars($mouvement['produit_reference']) ?></span>
                                            <?php if ($mouvement['categorie_nom']): ?>
                                                <span class="product-category"><?= htmlspecialchars($mouvement['categorie_nom']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="movement-time">
                                            <?= date('H:i', strtotime($mouvement['date_mouvement'])) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="movement-details">
                                        <div class="movement-type-badge <?= $mouvement['type_mouvement'] ?>">
                                            <?= ucfirst($mouvement['type_mouvement']) ?>
                                        </div>
                                        
                                        <div class="movement-quantity">
                                            <?php if ($mouvement['type_mouvement'] === 'entree'): ?>
                                                <span class="quantity-change positive">+<?= $mouvement['quantite'] ?></span>
                                            <?php elseif ($mouvement['type_mouvement'] === 'sortie'): ?>
                                                <span class="quantity-change negative">-<?= $mouvement['quantite'] ?></span>
                                            <?php else: ?>
                                                <span class="quantity-change neutral">±<?= $mouvement['quantite'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="stock-evolution">
                                            <span class="stock-before"><?= $mouvement['quantite_avant'] ?></span>
                                            <i class="fas fa-arrow-right"></i>
                                            <span class="stock-after"><?= $mouvement['quantite_apres'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($mouvement['motif']): ?>
                                        <div class="movement-reason">
                                            <i class="fas fa-comment"></i>
                                            <?= htmlspecialchars($mouvement['motif']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="movement-metadata">
                                        <?php if ($mouvement['reference_document']): ?>
                                            <div class="metadata-item">
                                                <i class="fas fa-file-alt"></i>
                                                <span>Réf: <?= htmlspecialchars($mouvement['reference_document']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($mouvement['prenom'] && $mouvement['user_nom']): ?>
                                            <div class="metadata-item">
                                                <i class="fas fa-user"></i>
                                                <span><?= htmlspecialchars($mouvement['prenom'] . ' ' . $mouvement['user_nom']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($mouvement['cout_unitaire']): ?>
                                            <div class="metadata-item">
                                                <i class="fas fa-euro-sign"></i>
                                                <span>Coût: <?= formatPrice($mouvement['cout_unitaire']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($mouvements) >= $limit): ?>
                        <div class="load-more-section">
                            <p>Affichage des <?= $limit ?> mouvements les plus récents.</p>
                            <a href="?<?= http_build_query(array_merge($_GET, ['limit' => min($limit * 2, 200)])) ?>" class="btn-border">
                                <i class="fas fa-chevron-down"></i> Afficher plus de résultats
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .history-container {
            margin: 0 30px;
        }

        .product-header-card {
            background: white;
            margin: 0 30px 20px;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary-color);
        }

        .product-header-info {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .product-main-info h3 {
            margin: 0 0 5px 0;
            color: var(--secondary-color);
            font-size: 20px;
        }

        .product-main-info p {
            margin: 0 0 8px 0;
            color: #666;
            font-size: 14px;
        }

        .product-stock-info {
            display: flex;
            gap: 25px;
        }

        .stock-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .stock-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .stock-value {
            font-weight: 600;
            font-size: 16px;
            color: var(--secondary-color);
        }

        .stock-value.alerte {
            color: var(--warning-color);
        }

        .stock-value.rupture {
            color: var(--danger-color);
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .filters-section {
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid var(--border-color);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .movements-timeline {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .date-separator {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .date-label {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 16px;
        }

        .movement-timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 25px 30px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            transition: all 0.3s ease;
        }

        .movement-timeline-item:hover {
            background: #f8f9fa;
        }

        .movement-timeline-item:last-child {
            border-bottom: none;
        }

        .movement-timeline-item::before {
            content: '';
            position: absolute;
            left: 50px;
            top: 80px;
            bottom: -25px;
            width: 2px;
            background: #e9ecef;
        }

        .movement-timeline-item:last-child::before {
            display: none;
        }

        .movement-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            position: relative;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .movement-timeline-item.entree .movement-icon {
            background: var(--success-color);
        }

        .movement-timeline-item.sortie .movement-icon {
            background: var(--danger-color);
        }

        .movement-timeline-item.ajustement .movement-icon {
            background: var(--info-color);
        }

        .movement-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .movement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .movement-product strong {
            color: var(--secondary-color);
            font-size: 16px;
            margin-right: 10px;
        }

        .product-ref {
            color: #666;
            font-size: 13px;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 8px;
        }

        .product-category {
            color: var(--primary-color);
            font-size: 12px;
            background: rgba(255, 107, 53, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
        }

        .movement-time {
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        .movement-details {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .movement-type-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .movement-type-badge.entree {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .movement-type-badge.sortie {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .movement-type-badge.ajustement {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info-color);
        }

        .movement-quantity {
            font-size: 18px;
            font-weight: 700;
        }

        .quantity-change.positive {
            color: var(--success-color);
        }

        .quantity-change.negative {
            color: var(--danger-color);
        }

        .quantity-change.neutral {
            color: var(--info-color);
        }

        .stock-evolution {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .stock-evolution i {
            color: #666;
            font-size: 12px;
        }

        .movement-reason {
            background: #f8f9fa;
            padding: 10px 12px;
            border-radius: 8px;
            font-style: italic;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid var(--primary-color);
        }

        .movement-metadata {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .metadata-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 13px;
        }

        .metadata-item i {
            color: var(--primary-color);
            width: 12px;
            text-align: center;
        }

        .load-more-section {
            text-align: center;
            padding: 30px;
            background: white;
            border-top: 1px solid #eee;
        }

        .load-more-section p {
            margin-bottom: 15px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .product-header-card {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }

            .product-header-info {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .product-stock-info {
                gap: 15px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-select {
                width: 100%;
            }

            .movement-timeline-item {
                padding: 20px 15px;
            }

            .movement-timeline-item::before {
                left: 35px;
            }

            .movement-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .movement-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .movement-metadata {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>

    <script>
        // Auto-refresh si on est sur la page d'un produit spécifique
        <?php if ($produit_id): ?>
        setInterval(function() {
            // Vérification silencieuse des nouveaux mouvements
            if (!document.hidden) {
                fetch('../../api/check_movements.php?produit=<?= $produit_id ?>&last_check=' + Math.floor(Date.now() / 1000))
                    .then(response => response.json())
                    .then(data => {
                        if (data.new_movements && data.new_movements > 0) {
                            // Afficher une notification discrète
                            showNewMovementsNotification(data.new_movements);
                        }
                    })
                    .catch(error => {
                        // Erreur silencieuse
                    });
            }
        }, 30000); // Toutes les 30 secondes
        <?php endif; ?>

        function showNewMovementsNotification(count) {
            // Créer une notification en haut de page
            const notification = document.createElement('div');
            notification.className = 'new-movements-notification';
            notification.innerHTML = `
                <i class="fas fa-info-circle"></i>
                ${count} nouveau(x) mouvement(s) détecté(s).
                <button onclick="location.reload()" class="btn-small">Actualiser</button>
                <button onclick="this.parentElement.remove()" class="btn-close">×</button>
            `;
            
            // Insérer en haut du contenu principal
            const mainContent = document.querySelector('.main-content');
            mainContent.insertBefore(notification, mainContent.children[1]);
            
            // Auto-suppression après 10 secondes
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 10000);
        }

        // Smooth scroll pour les ancres
        document.addEventListener('DOMContentLoaded', function() {
            // Animation d'apparition des éléments
            const timelineItems = document.querySelectorAll('.movement-timeline-item');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateX(0)';
                    }
                });
            }, { threshold: 0.1 });

            timelineItems.forEach(item => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(item);
            });
        });
    </script>
    
    <style>
        .new-movements-notification {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px 30px;
            margin: 0 30px 20px;
            border-radius: 8px;
            border: 1px solid #bee5eb;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .new-movements-notification .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            background: var(--info-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: auto;
        }

        .new-movements-notification .btn-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 0 5px;
            color: #0c5460;
        }
    </style>
</body>
</html>