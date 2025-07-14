<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';

// Mise à jour du statut si demandé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $commande_id = (int)$_POST['commande_id'];
        $nouveau_statut = sanitizeInput($_POST['nouveau_statut']);
        
        $result = $manager->mettreAJourStatutCommande($commande_id, $nouveau_statut);
        
        if ($result) {
            logActivite('update_statut_commande', [
                'commande_id' => $commande_id,
                'nouveau_statut' => $nouveau_statut
            ], $user['id']);
            
            $message = "Statut de la commande mis à jour avec succès";
        } else {
            $error = "Erreur lors de la mise à jour du statut";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des commandes
$commandes = $manager->getCommandes($statut, null, $search);

$message = $_GET['message'] ?? $message ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des commandes - SOTA Fashion</title>
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
                <h1><i class="fas fa-shopping-cart"></i> Gestion des commandes</h1>
                <p class="dashboard-subtitle">Suivi des commandes clients - <?= count($commandes) ?> commandes</p>
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

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher (n° commande, client...)" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <select name="statut" class="filter-select">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente" <?= $statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="confirmee" <?= $statut === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                        <option value="en_preparation" <?= $statut === 'en_preparation' ? 'selected' : '' ?>>En préparation</option>
                        <option value="expediee" <?= $statut === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                        <option value="livree" <?= $statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                        <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                    </select>

                    <button type="submit" class="btn-orange">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                    
                    <a href="commandes.php" class="btn-border">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                </form>
            </section>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <?php if (in_array($user['role'], ['Admin', 'Commercial'])): ?>
                        <a href="nouvelle.php" class="btn-orange">
                            <i class="fas fa-plus"></i> Nouvelle commande
                        </a>
                    <?php endif; ?>
                    <a href="export.php?format=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $statut ? '&statut=' . $statut : '' ?>" 
                       class="btn-border">
                        <i class="fas fa-download"></i> Exporter CSV
                    </a>
                </div>
            </section>

            <!-- Liste des commandes -->
            <div class="orders-container">
                <?php if (empty($commandes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Aucune commande trouvée</h3>
                        <p>
                            <?= $search || $statut ? 'Aucune commande ne correspond à vos critères.' : 'Aucune commande enregistrée.' ?>
                        </p>
                        <?php if (!$search && !$statut && in_array($user['role'], ['Admin', 'Commercial'])): ?>
                            <a href="nouvelle.php" class="btn-orange">
                                <i class="fas fa-plus"></i> Créer la première commande
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="orders-table-container">
                        <table class="stock-table">
                            <thead>
                                <tr>
                                    <th>N° Commande</th>
                                    <th>Client</th>
                                    <th>Date</th>
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
                                            <div class="customer-info">
                                                <strong><?= htmlspecialchars($commande['client_nom']) ?></strong>
                                                <?php if ($commande['client_email']): ?>
                                                    <small><?= htmlspecialchars($commande['client_email']) ?></small>
                                                <?php endif; ?>
                                                <?php if ($commande['client_telephone']): ?>
                                                    <small><?= htmlspecialchars($commande['client_telephone']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <strong><?= formatDate($commande['date_commande']) ?></strong>
                                                <?php if ($commande['date_livraison_prevue']): ?>
                                                    <small>Livraison: <?= formatDate($commande['date_livraison_prevue']) ?></small>
                                                <?php endif; ?>
                                            </div>
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
                                                <?php if ($commande['total_ht']): ?>
                                                    <small>HT: <?= formatPrice($commande['total_ht']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="status-container">
                                                <?= getStatusBadge($commande['statut']) ?>
                                                <?php if (in_array($user['role'], ['Admin', 'Commercial', 'Préparateur'])): ?>
                                                    <button onclick="changerStatut(<?= $commande['id'] ?>, '<?= $commande['statut'] ?>')" 
                                                            class="btn-change-status" title="Changer le statut">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="details.php?id=<?= $commande['id'] ?>" class="btn-border btn-small" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (in_array($user['role'], ['Admin', 'Commercial'])): ?>
                                                    <a href="modifier.php?id=<?= $commande['id'] ?>" class="btn-border btn-small" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (in_array($commande['statut'], ['confirmee', 'en_preparation']) && in_array($user['role'], ['Admin', 'Préparateur'])): ?>
                                                    <a href="../livraisons/nouvelle.php?commande_id=<?= $commande['id'] ?>" class="btn-border btn-small" title="Planifier livraison">
                                                        <i class="fas fa-truck"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($commande['statut'] === 'livree' && $user['role'] === 'Admin'): ?>
                                                    <button onclick="syncSage(<?= $commande['id'] ?>)" class="btn-border btn-small" title="Sync SAGE">
                                                        <i class="fas fa-sync"></i>
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

    <!-- Modal de changement de statut -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Changer le statut</h3>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="commande_id" id="modal_commande_id">
                
                <div class="form-group">
                    <label for="modal_nouveau_statut">Nouveau statut :</label>
                    <select name="nouveau_statut" id="modal_nouveau_statut" class="form-control" required>
                        <option value="en_attente">En attente</option>
                        <option value="confirmee">Confirmée</option>
                        <option value="en_preparation">En préparation</option>
                        <option value="expediee">Expédiée</option>
                        <option value="livree">Livrée</option>
                        <option value="annulee">Annulée</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeModal()" class="btn-border">Annuler</button>
                    <button type="submit" class="btn-orange">Confirmer</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .orders-container {
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

        .order-number, .customer-info, .date-info, .products-info, .amount-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .order-number strong, .customer-info strong, .date-info strong, 
        .products-info strong, .amount-info strong {
            color: #2c3e50;
            font-size: 14px;
        }

        .order-number small, .customer-info small, .date-info small, 
        .products-info small, .amount-info small {
            color: #666;
            font-size: 12px;
        }

        .status-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-change-status {
            background: none;
            border: 1px solid #ddd;
            color: #666;
            padding: 4px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.3s ease;
        }

        .btn-change-status:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .orders-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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

        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-form {
            padding: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>

    <script>
        function changerStatut(commandeId, statutActuel) {
            document.getElementById('modal_commande_id').value = commandeId;
            document.getElementById('modal_nouveau_statut').value = statutActuel;
            document.getElementById('statusModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function syncSage(commandeId) {
            if (confirm('Synchroniser cette commande avec SAGE ?')) {
                fetch('../../api/sage_sync_commande.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        commande_id: commandeId,
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Synchronisation SAGE réussie');
                        location.reload();
                    } else {
                        alert('Erreur lors de la synchronisation : ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur de connexion');
                });
            }
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Fermer le modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>