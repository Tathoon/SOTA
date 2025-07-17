<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$search = $_GET['search'] ?? '';

// Récupération des fournisseurs
$fournisseurs = $manager->getFournisseurs($search);

// Statistiques des fournisseurs
try {
    $stmt = $manager->db->prepare("
        SELECT 
            COUNT(*) as total_fournisseurs,
            COUNT(CASE WHEN actif = 1 THEN 1 END) as fournisseurs_actifs,
            AVG(delais_livraison) as delai_moyen
        FROM fournisseurs
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Nombre de commandes fournisseurs
    $stmt = $manager->db->prepare("SELECT COUNT(*) as total FROM commandes_fournisseurs");
    $stmt->execute();
    $stats['total_commandes'] = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $stats = ['total_fournisseurs' => 0, 'fournisseurs_actifs' => 0, 'delai_moyen' => 0, 'total_commandes' => 0];
}

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des fournisseurs - SOTA Fashion</title>
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
                <h1><i class="fas fa-truck-loading"></i> Gestion des fournisseurs</h1>
                <p class="dashboard-subtitle">Partenaires prêt-à-porter féminin - <?= count($fournisseurs) ?> fournisseurs</p>
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

            <!-- Statistiques fournisseurs -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-truck-loading icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats['total_fournisseurs'] ?></h3>
                        <p>Fournisseurs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle icon" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['fournisseurs_actifs'] ?></h3>
                        <p>Actifs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #3498db;"></i>
                    <div class="stat-content">
                        <h3><?= round($stats['delai_moyen']) ?> j</h3>
                        <p>Délai moyen</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shopping-cart icon" style="color: #f39c12;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['total_commandes'] ?></h3>
                        <p>Commandes passées</p>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher un fournisseur (nom, contact, ville...)" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <button type="submit" class="btn-orange">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                    
                    <a href="fournisseurs.php" class="btn-border">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                </form>
            </section>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="nouveau.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouveau fournisseur
                    </a>
                    <a href="commandes_fournisseurs.php" class="btn-border">
                        <i class="fas fa-shopping-cart"></i> Commandes fournisseurs
                    </a>
                </div>
            </section>

            <!-- Liste des fournisseurs -->
            <div class="suppliers-container">
                <?php if (empty($fournisseurs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-truck-loading"></i>
                        <h3>Aucun fournisseur trouvé</h3>
                        <p>
                            <?= $search ? 'Aucun fournisseur ne correspond à votre recherche.' : 'Aucun fournisseur enregistré.' ?>
                        </p>
                        <?php if (!$search): ?>
                            <a href="nouveau.php" class="btn-orange">
                                <i class="fas fa-plus"></i> Ajouter le premier fournisseur
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="suppliers-grid">
                        <?php foreach ($fournisseurs as $fournisseur): ?>
                            <div class="supplier-card">
                                <div class="supplier-header">
                                    <div class="supplier-info">
                                        <h3><?= htmlspecialchars($fournisseur['nom']) ?></h3>
                                        <?php if ($fournisseur['specialite_mode']): ?>
                                            <p class="specialite"><?= htmlspecialchars($fournisseur['specialite_mode']) ?></p>
                                        <?php endif; ?>
                                        <div class="supplier-badges">
                                            <?php if ($fournisseur['actif']): ?>
                                                <span class="badge active">Actif</span>
                                            <?php else: ?>
                                                <span class="badge inactive">Inactif</span>
                                            <?php endif; ?>
                                            <?php if ($fournisseur['note_qualite']): ?>
                                                <span class="badge rating">
                                                    <i class="fas fa-star"></i> <?= $fournisseur['note_qualite'] ?>/5
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="supplier-content">
                                    <div class="contact-info">
                                        <?php if ($fournisseur['contact']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-user"></i>
                                                <span><?= htmlspecialchars($fournisseur['contact']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fournisseur['telephone']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-phone"></i>
                                                <a href="tel:<?= htmlspecialchars($fournisseur['telephone']) ?>">
                                                    <?= htmlspecialchars($fournisseur['telephone']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fournisseur['email']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-envelope"></i>
                                                <a href="mailto:<?= htmlspecialchars($fournisseur['email']) ?>">
                                                    <?= htmlspecialchars($fournisseur['email']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fournisseur['ville']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?= htmlspecialchars($fournisseur['ville']) ?> (<?= htmlspecialchars($fournisseur['pays']) ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="business-info">
                                        <div class="info-item">
                                            <label>Délai livraison</label>
                                            <span><?= $fournisseur['delais_livraison'] ?> jours</span>
                                        </div>
                                        
                                        <?php if ($fournisseur['conditions_paiement']): ?>
                                            <div class="info-item">
                                                <label>Conditions paiement</label>
                                                <span><?= htmlspecialchars($fournisseur['conditions_paiement']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fournisseur['siret']): ?>
                                            <div class="info-item">
                                                <label>SIRET</label>
                                                <span><?= htmlspecialchars($fournisseur['siret']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="supplier-actions">
                                    <a href="details.php?id=<?= $fournisseur['id'] ?>" class="btn-border btn-small">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                    <a href="modifier.php?id=<?= $fournisseur['id'] ?>" class="btn-border btn-small">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="commandes_fournisseurs.php?fournisseur=<?= $fournisseur['id'] ?>" class="btn-orange btn-small">
                                        <i class="fas fa-shopping-cart"></i> Commandes
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .suppliers-container {
            margin: 0 30px;
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

        .suppliers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        .supplier-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .supplier-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .supplier-header {
            padding: 25px 25px 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .supplier-info h3 {
            margin: 0 0 8px 0;
            color: var(--secondary-color);
            font-size: 18px;
            font-weight: 600;
        }

        .specialite {
            margin: 0 0 12px 0;
            color: var(--primary-color);
            font-style: italic;
            font-size: 14px;
        }

        .supplier-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.active {
            background: #d4edda;
            color: #155724;
        }

        .badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.rating {
            background: #fff3cd;
            color: #856404;
        }

        .supplier-content {
            padding: 20px 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .contact-info,
        .business-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .info-row i {
            color: var(--primary-color);
            width: 16px;
            text-align: center;
        }

        .info-row a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .info-row a:hover {
            text-decoration: underline;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .info-item label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .info-item span {
            font-size: 13px;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .supplier-actions {
            padding: 20px 25px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .supplier-actions .btn-small {
            flex: 1;
            min-width: 0;
            text-align: center;
            padding: 8px 12px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .suppliers-grid {
                grid-template-columns: 1fr;
            }

            .supplier-content {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .supplier-actions {
                flex-direction: column;
            }

            .supplier-actions .btn-small {
                flex: none;
            }
        }
    </style>
</body>
</html>