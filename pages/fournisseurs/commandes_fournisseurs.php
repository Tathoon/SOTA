<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$fournisseur_id = !empty($_GET['fournisseur']) ? (int)$_GET['fournisseur'] : null;
$statut = $_GET['statut'] ?? '';
$search = $_GET['search'] ?? '';

// Récupération des commandes fournisseurs
$commandes = $manager->getCommandesFournisseurs($fournisseur_id);

// Filtrage manuel pour search et statut
if ($search || $statut) {
    $commandes = array_filter($commandes, function($cmd) use ($search, $statut) {
        $matchSearch = !$search || 
            stripos($cmd['numero_commande'], $search) !== false ||
            stripos($cmd['fournisseur_nom'], $search) !== false;
        
        $matchStatut = !$statut || $cmd['statut'] === $statut;
        
        return $matchSearch && $matchStatut;
    });
}

// Récupération des fournisseurs pour le filtre
$fournisseurs = $manager->getFournisseurs();

// Fournisseur sélectionné
$fournisseur_selectionne = null;
if ($fournisseur_id) {
    $fournisseur_selectionne = $manager->getFournisseurById($fournisseur_id);
}

// Statistiques
$stats = [
    'total' => count($manager->getCommandesFournisseurs()),
    'en_attente' => count(array_filter($manager->getCommandesFournisseurs(), fn($c) => $c['statut'] === 'en_attente')),
    'confirmee' => count(array_filter($manager->getCommandesFournisseurs(), fn($c) => $c['statut'] === 'confirmee')),
    'recue' => count(array_filter($manager->getCommandesFournisseurs(), fn($c) => $c['statut'] === 'recue'))
];

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes fournisseurs - SOTA Fashion</title>
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
                <h1><i class="fas fa-shopping-cart"></i> Commandes fournisseurs</h1>
                <p class="dashboard-subtitle">
                    Gestion des approvisionnements - <?= count($commandes) ?> commandes
                    <?= $fournisseur_selectionne ? ' pour ' . htmlspecialchars($fournisseur_selectionne['nom']) : '' ?>
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

            <!-- Informations du fournisseur sélectionné -->
            <?php if ($fournisseur_selectionne): ?>
                <div class="supplier-header-card">
                    <div class="supplier-info">
                        <h3><?= htmlspecialchars($fournisseur_selectionne['nom']) ?></h3>
                        <p><?= htmlspecialchars($fournisseur_selectionne['specialite_mode'] ?: 'Fournisseur mode') ?></p>
                        <div class="supplier-details">
                            <span><i class="fas fa-clock"></i> <?= $fournisseur_selectionne['delais_livraison'] ?> jours</span>
                            <span><i class="fas fa-credit-card"></i> <?= htmlspecialchars($fournisseur_selectionne['conditions_paiement'] ?: 'Non défini') ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($fournisseur_selectionne['ville']) ?></span>
                        </div>
                    </div>
                    <div class="supplier-actions">
                        <a href="details.php?id=<?= $fournisseur_selectionne['id'] ?>" class="btn-border">
                            <i class="fas fa-eye"></i> Voir fournisseur
                        </a>
                        <a href="nouvelle_commande.php?fournisseur=<?= $fournisseur_selectionne['id'] ?>" class="btn-orange">
                            <i class="fas fa-plus"></i> Nouvelle commande
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-shopping-cart icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Total commandes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #3498db;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['en_attente'] ?></h3>
                        <p>En attente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle icon" style="color: #f39c12;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['confirmee'] ?></h3>
                        <p>Confirmées</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-truck icon" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['recue'] ?></h3>
                        <p>Reçues</p>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-row">
                        <select name="fournisseur" class="filter-select">
                            <option value="">Tous les fournisseurs</option>
                            <?php foreach ($fournisseurs as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= $fournisseur_id == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="statut" class="filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?= $statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="confirmee" <?= $statut === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                            <option value="expediee" <?= $statut === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                            <option value="recue" <?= $statut === 'recue' ? 'selected' : '' ?>>Reçue</option>
                            <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                        
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Rechercher (n° commande, fournisseur...)" 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-orange">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        
                        <a href="commandes_fournisseurs.php" class="btn-border">
                            <i class="fas fa-times"></i> Effacer
                        </a>
                    </div>
                </form>
            </section>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="nouvelle_commande.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouvelle commande
                    </a>
                    <a href="fournisseurs.php" class="btn-border">
                        <i class="fas fa-truck-loading"></i> Retour fournisseurs
                    </a>
                </div>
            </section>

            <!-- Liste des commandes -->
            <div class="supplier-orders-container">
                <?php if (empty($commandes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Aucune commande trouvée</h3>
                        <p>
                            <?= ($search || $statut || $fournisseur_id) ? 
                                'Aucune commande ne correspond à vos critères.' : 
                                'Aucune commande fournisseur enregistrée.' ?>
                        </p>
                        <a href="nouvelle_commande.php<?= $fournisseur_id ? '?fournisseur=' . $fournisseur_id : '' ?>" class="btn-orange">
                            <i class="fas fa-plus"></i> Créer la première commande
                        </a>
                    </div>
                <?php else: ?>
                    <div class="orders-table-container">
                        <table class="stock-table">
                            <thead>
                                <tr>
                                    <th>N° Commande</th>
                                    <th>Fournisseur</th>
                                    <th>Date commande</th>
                                    <th>Date livraison</th>
                                    <th>Produits</th>
                                    <th>Total</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes as $commande): ?>
                                    <tr>
                                        <td>
                                            <div class="order-number">
                                                <strong><?= htmlspecialchars($commande['numero_commande']) ?></strong>
                                                <small><?= formatDateTime($commande['created_at']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="supplier-info">
                                                <strong><?= htmlspecialchars($commande['fournisseur_nom']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span><?= formatDate($commande['date_commande']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($commande['date_livraison_reelle']): ?>
                                                <div class="delivery-dates">
                                                    <strong class="delivered"><?= formatDate($commande['date_livraison_reelle']) ?></strong>
                                                    <small>Livrée</small>
                                                </div>
                                            <?php elseif ($commande['date_livraison_prevue']): ?>
                                                <div class="delivery-dates">
                                                    <span><?= formatDate($commande['date_livraison_prevue']) ?></span>
                                                    <small>Prévue</small>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #999;">Non définie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="products-info">
                                                <strong><?= $commande['nb_produits'] ?> produit(s)</strong>
                                                <small><?= $commande['quantite_totale'] ?> article(s)</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="amount-info">
                                                <strong><?= formatPrice($commande['total']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= getStatusBadge($commande['statut']) ?></td>
                                        <td>
                                            <div class="actions">
                                                <a href="details_commande.php?id=<?= $commande['id'] ?>" 
                                                   class="btn-border btn-small" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (in_array($commande['statut'], ['en_attente', 'confirmee'])): ?>
                                                    <a href="modifier_commande.php?id=<?= $commande['id'] ?>" 
                                                       class="btn-border btn-small" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($commande['statut'] === 'expediee'): ?>
                                                    <button onclick="marquerRecue(<?= $commande['id'] ?>)" 
                                                            class="btn-orange btn-small" title="Marquer comme reçue">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .supplier-orders-container {
            margin: 0 30px;
        }

        .supplier-header-card {
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

        .supplier-info h3 {
            margin: 0 0 5px 0;
            color: var(--secondary-color);
            font-size: 20px;
        }

        .supplier-info p {
            margin: 0 0 10px 0;
            color: #666;
            font-style: italic;
        }

        .supplier-details {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
        }

        .supplier-details span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .supplier-actions {
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

        .orders-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .order-number, .supplier-info, .products-info, .amount-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .order-number strong, .supplier-info strong, .products-info strong, .amount-info strong {
            color: #2c3e50;
            font-size: 14px;
        }

        .order-number small, .supplier-info small, .products-info small {
            color: #666;
            font-size: 12px;
        }

        .delivery-dates {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .delivery-dates .delivered {
            color: #27ae60;
            font-weight: 600;
        }

        .delivery-dates small {
            color: #666;
            font-size: 11px;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .actions .btn-small {
            padding: 6px 8px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .supplier-header-card {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }

            .supplier-details {
                flex-direction: column;
                gap: 8px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-select, .search-box {
                width: 100%;
            }
        }
    </style>

    <script>
        function marquerRecue(commandeId) {
            if (confirm('Marquer cette commande comme reçue ?')) {
                fetch('../../api/update_commande_fournisseur.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        commande_id: commandeId,
                        statut: 'recue',
                        date_livraison_reelle: new Date().toISOString().split('T')[0],
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la mise à jour : ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur de connexion');
                });
            }
        }
    </script>
</body>
</html>