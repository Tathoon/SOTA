<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$produit_id = (int)($_GET['id'] ?? 0);

// Récupération du produit
$produit = $manager->getProduitById($produit_id);
if (!$produit) {
    header('Location: produits.php?error=' . urlencode('Lot non trouvé'));
    exit();
}

// Récupération de l'historique des mouvements
$mouvements = $manager->getHistoriqueMouvements($produit_id, 10);

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails lot - SOTA Fashion</title>
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
                <h1><i class="fas fa-tshirt"></i> <?= htmlspecialchars($produit['nom']) ?></h1>
                <p class="dashboard-subtitle">Référence: <?= htmlspecialchars($produit['reference']) ?></p>
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

            <!-- Actions rapides -->
            <div class="stock-actions">
                <a href="modifier.php?id=<?= $produit['id'] ?>" class="btn-orange">
                    <i class="fas fa-edit"></i> Modifier
                </a>
                <a href="../stocks/mouvement.php?produit=<?= $produit['id'] ?>" class="btn-border">
                    <i class="fas fa-exchange-alt"></i> Mouvement stock
                </a>
                <a href="../stocks/historique.php?produit=<?= $produit['id'] ?>" class="btn-border">
                    <i class="fas fa-history"></i> Historique
                </a>
                <a href="produits.php" class="btn-border">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <div class="dashboard-grid">
                <!-- Informations principales -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-info-circle"></i> Informations générales</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-warehouse icon"></i>
                            <div class="stat-content">
                                <h3><?= $produit['stock_actuel'] ?></h3>
                                <p>Stock actuel</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-euro-sign icon"></i>
                            <div class="stat-content">
                                <h3><?= formatPrice($produit['prix_vente']) ?></h3>
                                <p>Prix de vente</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-chart-line icon"></i>
                            <div class="stat-content">
                                <h3>
                                    <?php if ($produit['prix_achat']): ?>
                                        <?= number_format((($produit['prix_vente'] - $produit['prix_achat']) / $produit['prix_achat']) * 100, 1) ?>%
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </h3>
                                <p>Marge</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <h3><?= getStatusBadge($produit['statut_stock']) ?></h3>
                                <p>Statut stock</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nom du lot</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['nom']) ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Référence</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['reference']) ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Catégorie</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['categorie_nom'] ?? 'Non classé') ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Marque</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['marque'] ?: 'Non définie') ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Collection</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['collection'] ?: 'Non définie') ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Seuil minimum</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= $produit['seuil_minimum'] ?> unités</div>
                        </div>
                    </div>

                    <?php if ($produit['description']): ?>
                    <div class="form-group">
                        <label>Description</label>
                        <div class="form-control" style="background: #f8f9fa; min-height: 60px;"><?= nl2br(htmlspecialchars($produit['description'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Caractéristiques -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-tags"></i> Caractéristiques</h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Taille</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['taille'] ?: 'Non définie') ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Couleur</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['couleur'] ?: 'Non définie') ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Saison</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['saison'] ?: 'Non définie') ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Poids</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= $produit['poids'] ? $produit['poids'] . ' g' : 'Non défini' ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Lot minimum</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= $produit['lot_minimum'] ?> unité(s)</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Emplacement</label>
                            <div class="form-control" style="background: #f8f9fa;"><?= htmlspecialchars($produit['emplacement'] ?: 'Non défini') ?></div>
                        </div>
                    </div>

                    <?php if ($produit['composition']): ?>
                    <div class="form-group">
                        <label>Composition</label>
                        <div class="form-control" style="background: #f8f9fa; min-height: 60px;"><?= nl2br(htmlspecialchars($produit['composition'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Prix et valorisation -->
            <div class="dashboard-section">
                <h2><i class="fas fa-euro-sign"></i> Prix et valorisation</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Prix d'achat</label>
                        <div class="form-control" style="background: #f8f9fa;"><?= $produit['prix_achat'] ? formatPrice($produit['prix_achat']) : 'Non défini' ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Prix de vente</label>
                        <div class="form-control" style="background: #f8f9fa;"><?= formatPrice($produit['prix_vente']) ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Marge unitaire</label>
                        <div class="form-control" style="background: #f8f9fa;">
                            <?php if ($produit['prix_achat']): ?>
                                <?= formatPrice($produit['prix_vente'] - $produit['prix_achat']) ?>
                            <?php else: ?>
                                Non calculée
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Valeur stock</label>
                        <div class="form-control" style="background: #f8f9fa;">
                            <?php if ($produit['prix_achat']): ?>
                                <?= formatPrice($produit['stock_actuel'] * $produit['prix_achat']) ?>
                            <?php else: ?>
                                Non calculée
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique récent -->
            <?php if (!empty($mouvements)): ?>
            <div class="dashboard-section">
                <h2><i class="fas fa-history"></i> Mouvements récents</h2>
                
                <div class="recent-list">
                    <?php foreach ($mouvements as $mouvement): ?>
                    <div class="recent-item">
                        <div>
                            <strong><?= ucfirst($mouvement['type_mouvement']) ?></strong>
                            <small><?= formatDateTime($mouvement['date_mouvement']) ?></small>
                        </div>
                        <div>
                            Quantité: <?= $mouvement['type_mouvement'] === 'entree' ? '+' : ($mouvement['type_mouvement'] === 'sortie' ? '-' : '±') ?><?= $mouvement['quantite'] ?>
                        </div>
                        <div>
                            <?= $mouvement['quantite_avant'] ?> → <?= $mouvement['quantite_apres'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <a href="../stocks/historique.php?produit=<?= $produit['id'] ?>" class="view-all">
                    Voir tout l'historique →
                </a>
            </div>
            <?php endif; ?>

            <!-- Métadonnées -->
            <div class="dashboard-section">
                <h2><i class="fas fa-info"></i> Métadonnées</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date de création</label>
                        <div class="form-control" style="background: #f8f9fa;"><?= formatDateTime($produit['created_at']) ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Dernière modification</label>
                        <div class="form-control" style="background: #f8f9fa;"><?= formatDateTime($produit['updated_at']) ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>