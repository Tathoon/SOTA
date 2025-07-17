<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Livreur', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$statut = $_GET['statut'] ?? '';
$search = $_GET['search'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Mise à jour du statut si demandé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_livraison'])) {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $livraison_id = (int)$_POST['livraison_id'];
        $data = [
            'date_prevue' => $_POST['date_prevue'],
            'date_reelle' => $_POST['date_reelle'] ?: null,
            'transporteur' => sanitizeInput($_POST['transporteur']),
            'numero_suivi' => sanitizeInput($_POST['numero_suivi']),
            'statut' => sanitizeInput($_POST['statut']),
            'notes' => sanitizeInput($_POST['notes'])
        ];
        
        $result = $manager->mettreAJourLivraison($livraison_id, $data);
        
        if ($result) {
            logActivite('update_livraison', [
                'livraison_id' => $livraison_id,
                'nouveau_statut' => $data['statut']
            ], $user['id']);
            
            $message = "Livraison mise à jour avec succès";
        } else {
            $error = "Erreur lors de la mise à jour de la livraison";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Construction de la requête avec filtres
try {
    $sql = "
        SELECT l.*, c.numero_commande, c.client_nom, c.client_email, c.client_telephone, 
               c.total, c.client_adresse, c.client_ville, c.client_code_postal,
               COUNT(dc.id) as nb_produits, SUM(dc.quantite) as quantite_totale
        FROM livraisons l
        JOIN commandes c ON l.commande_id = c.id
        LEFT JOIN details_commandes dc ON c.id = dc.commande_id
        WHERE 1=1
    ";
    $params = [];

    if ($statut) {
        $sql .= " AND l.statut = ?";
        $params[] = $statut;
    }

    if ($search) {
        $sql .= " AND (c.numero_commande LIKE ? OR c.client_nom LIKE ? OR l.numero_suivi LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($date_debut) {
        $sql .= " AND l.date_prevue >= ?";
        $params[] = $date_debut;
    }

    if ($date_fin) {
        $sql .= " AND l.date_prevue <= ?";
        $params[] = $date_fin;
    }

    $sql .= " GROUP BY l.id ORDER BY l.date_prevue ASC";

    $stmt = $manager->db->prepare($sql);
    $stmt->execute($params);
    $livraisons = $stmt->fetchAll();

} catch (Exception $e) {
    $livraisons = [];
    $error = "Erreur lors du chargement des livraisons";
}

// Statistiques des livraisons
$stats_livraisons = [
    'total' => count($manager->getLivraisons()),
    'planifiee' => count($manager->getLivraisons('planifiee')),
    'en_cours' => count($manager->getLivraisons('en_cours')),
    'livree' => count($manager->getLivraisons('livree')),
    'echec' => count($manager->getLivraisons('echec'))
];

$message = $_GET['message'] ?? $message ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des livraisons - SOTA Fashion</title>
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
                <h1><i class="fas fa-truck"></i> Gestion des livraisons</h1>
                <p class="dashboard-subtitle">Suivi et planification des livraisons - <?= count($livraisons) ?> livraisons</p>
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

            <!-- Statistiques des livraisons -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-truck icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats_livraisons['total'] ?></h3>
                        <p>Total livraisons</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #3498db;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_livraisons['planifiee'] ?></h3>
                        <p>Planifiées</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shipping-fast icon" style="color: #f39c12;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_livraisons['en_cours'] ?></h3>
                        <p>En cours</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle icon" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_livraisons['livree'] ?></h3>
                        <p>Livrées</p>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-row">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Rechercher (commande, client, suivi...)" 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <select name="statut" class="filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="planifiee" <?= $statut === 'planifiee' ? 'selected' : '' ?>>Planifiée</option>
                            <option value="en_cours" <?= $statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="livree" <?= $statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                            <option value="echec" <?= $statut === 'echec' ? 'selected' : '' ?>>Échec</option>
                        </select>
                        
                        <input type="date" name="date_debut" class="filter-select" 
                               value="<?= htmlspecialchars($date_debut) ?>" placeholder="Date début">
                        
                        <input type="date" name="date_fin" class="filter-select" 
                               value="<?= htmlspecialchars($date_fin) ?>" placeholder="Date fin">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-orange">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        
                        <a href="livraisons.php" class="btn-border">
                            <i class="fas fa-times"></i> Effacer
                        </a>
                    </div>
                </form>
            </section>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <?php if (in_array($user['role'], ['Admin', 'Préparateur'])): ?>
                        <a href="nouvelle.php" class="btn-orange">
                            <i class="fas fa-plus"></i> Nouvelle livraison
                        </a>
                    <?php endif; ?>
                    <a href="planning.php" class="btn-border">
                        <i class="fas fa-calendar-alt"></i> Planning livraisons
                    </a>
                </div>
            </section>

            <!-- Liste des livraisons -->
            <div class="deliveries-container">
                <?php if (empty($livraisons)): ?>
                    <div class="empty-state">
                        <i class="fas fa-truck"></i>
                        <h3>Aucune livraison trouvée</h3>
                        <p>
                            <?= ($search || $statut || $date_debut || $date_fin) ? 
                                'Aucune livraison ne correspond à vos critères.' : 
                                'Aucune livraison planifiée.' ?>
                        </p>
                        <?php if (!$search && !$statut && in_array($user['role'], ['Admin', 'Préparateur'])): ?>
                            <a href="nouvelle.php" class="btn-orange">
                                <i class="fas fa-plus"></i> Planifier la première livraison
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="deliveries-grid">
                        <?php foreach ($livraisons as $livraison): ?>
                            <div class="delivery-card <?= $livraison['statut'] ?>">
                                <div class="delivery-header">
                                    <div class="delivery-status">
                                        <?= getStatusBadge($livraison['statut']) ?>
                                        <span class="delivery-id">#<?= $livraison['id'] ?></span>
                                    </div>
                                    <div class="delivery-date">
                                        <?php
                                        $date_prevue = strtotime($livraison['date_prevue']);
                                        $today = strtotime(date('Y-m-d'));
                                        $diff_days = round(($date_prevue - $today) / (60 * 60 * 24));
                                        
                                        if ($diff_days < 0): ?>
                                            <span class="date-overdue">En retard de <?= abs($diff_days) ?> jour(s)</span>
                                        <?php elseif ($diff_days == 0): ?>
                                            <span class="date-today">Aujourd'hui</span>
                                        <?php elseif ($diff_days == 1): ?>
                                            <span class="date-tomorrow">Demain</span>
                                        <?php else: ?>
                                            <span class="date-future">Dans <?= $diff_days ?> jour(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="delivery-content">
                                    <div class="order-info">
                                        <h4><?= htmlspecialchars($livraison['numero_commande']) ?></h4>
                                        <p class="customer-name"><?= htmlspecialchars($livraison['client_nom']) ?></p>
                                        <div class="order-details">
                                            <span><?= $livraison['nb_produits'] ?> produit(s)</span>
                                            <span><?= $livraison['quantite_totale'] ?> article(s)</span>
                                            <span><?= formatPrice($livraison['total']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="delivery-info">
                                        <div class="info-row">
                                            <i class="fas fa-calendar"></i>
                                            <span>Prévue le <?= formatDate($livraison['date_prevue']) ?></span>
                                        </div>
                                        
                                        <?php if ($livraison['date_reelle']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-check"></i>
                                                <span>Livrée le <?= formatDate($livraison['date_reelle']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($livraison['transporteur']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-truck"></i>
                                                <span><?= htmlspecialchars($livraison['transporteur']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($livraison['numero_suivi']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-barcode"></i>
                                                <span class="tracking-number"><?= htmlspecialchars($livraison['numero_suivi']) ?></span>
                                                <button onclick="copyTracking('<?= htmlspecialchars($livraison['numero_suivi']) ?>')" 
                                                        class="btn-copy" title="Copier">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="info-row address-info">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <div class="address">
                                                <?php if ($livraison['adresse_livraison']): ?>
                                                    <?= nl2br(htmlspecialchars($livraison['adresse_livraison'])) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($livraison['client_adresse']) ?><br>
                                                    <?= htmlspecialchars($livraison['client_code_postal']) ?> <?= htmlspecialchars($livraison['client_ville']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($livraison['notes']): ?>
                                            <div class="info-row">
                                                <i class="fas fa-comment"></i>
                                                <span class="notes"><?= htmlspecialchars($livraison['notes']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="delivery-actions">
                                    <div class="contact-actions">
                                        <?php if ($livraison['client_telephone']): ?>
                                            <a href="tel:<?= htmlspecialchars($livraison['client_telephone']) ?>" 
                                               class="btn-contact" title="Appeler">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($livraison['client_email']): ?>
                                            <a href="mailto:<?= htmlspecialchars($livraison['client_email']) ?>" 
                                               class="btn-contact" title="Email">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($livraison['adresse_livraison'] || $livraison['client_adresse']): ?>
                                            <?php 
                                            $adresse_complete = $livraison['adresse_livraison'] ?: 
                                                ($livraison['client_adresse'] . ' ' . $livraison['client_code_postal'] . ' ' . $livraison['client_ville']);
                                            ?>
                                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($adresse_complete) ?>" 
                                               target="_blank" class="btn-contact" title="Voir sur carte">
                                                <i class="fas fa-map"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="main-actions">
                                        <a href="../commandes/details.php?id=<?= $livraison['commande_id'] ?>" 
                                           class="btn-border btn-small">
                                            <i class="fas fa-eye"></i> Commande
                                        </a>
                                        
                                        <?php if (in_array($user['role'], ['Admin', 'Livreur'])): ?>
                                            <button onclick="editDelivery(<?= $livraison['id'] ?>)" 
                                                    class="btn-orange btn-small">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de modification de livraison -->
    <div id="editDeliveryModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Modifier la livraison</h3>
                <button onclick="closeEditModal()" class="modal-close">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="update_livraison" value="1">
                <input type="hidden" name="livraison_id" id="edit_livraison_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_date_prevue">Date prévue :</label>
                        <input type="date" name="date_prevue" id="edit_date_prevue" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_date_reelle">Date réelle :</label>
                        <input type="date" name="date_reelle" id="edit_date_reelle" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_transporteur">Transporteur :</label>
                        <select name="transporteur" id="edit_transporteur" class="form-control">
                            <option value="">Sélectionner...</option>
                            <option value="Chronopost">Chronopost</option>
                            <option value="Colissimo">Colissimo</option>
                            <option value="DHL">DHL</option>
                            <option value="UPS">UPS</option>
                            <option value="FedEx">FedEx</option>
                            <option value="TNT">TNT</option>
                            <option value="Relais Colis">Relais Colis</option>
                            <option value="Mondial Relay">Mondial Relay</option>
                            <option value="Livraison propre">Livraison propre</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_numero_suivi">Numéro de suivi :</label>
                        <input type="text" name="numero_suivi" id="edit_numero_suivi" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_statut">Statut :</label>
                        <select name="statut" id="edit_statut" class="form-control" required>
                            <option value="planifiee">Planifiée</option>
                            <option value="en_cours">En cours</option>
                            <option value="livree">Livrée</option>
                            <option value="echec">Échec</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notes :</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeEditModal()" class="btn-border">Annuler</button>
                    <button type="submit" class="btn-orange">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .deliveries-container {
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

        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        .delivery-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border-left: 4px solid #ddd;
        }

        .delivery-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .delivery-card.planifiee {
            border-left-color: #3498db;
        }

        .delivery-card.en_cours {
            border-left-color: #f39c12;
        }

        .delivery-card.livree {
            border-left-color: #27ae60;
        }

        .delivery-card.echec {
            border-left-color: #e74c3c;
        }

        .delivery-header {
            padding: 20px 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .delivery-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .delivery-id {
            color: #666;
            font-size: 12px;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .date-overdue {
            color: #e74c3c;
            font-weight: 600;
            font-size: 12px;
        }

        .date-today {
            color: #f39c12;
            font-weight: 600;
            font-size: 12px;
        }

        .date-tomorrow {
            color: #3498db;
            font-weight: 600;
            font-size: 12px;
        }

        .date-future {
            color: #666;
            font-size: 12px;
        }

        .delivery-content {
            padding: 20px;
        }

        .order-info h4 {
            margin: 0 0 5px 0;
            color: var(--secondary-color);
            font-size: 16px;
        }

        .customer-name {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .order-details {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #666;
        }

        .delivery-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
        }

        .info-row i {
            color: var(--primary-color);
            width: 16px;
            text-align: center;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .tracking-number {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }

        .btn-copy {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            padding: 2px 4px;
            margin-left: 5px;
        }

        .address-info .address {
            line-height: 1.4;
        }

        .notes {
            font-style: italic;
            color: #666;
        }

        .delivery-actions {
            padding: 15px 20px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .contact-actions {
            display: flex;
            gap: 8px;
        }

        .btn-contact {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f8f9fa;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .btn-contact:hover {
            background: var(--primary-color);
            color: white;
        }

        .main-actions {
            display: flex;
            gap: 8px;
        }

        .modal-large {
            width: 90%;
            max-width: 600px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .deliveries-grid {
                grid-template-columns: 1fr;
            }

            .delivery-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .order-details {
                flex-direction: column;
                gap: 5px;
            }

            .delivery-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .contact-actions {
                justify-content: center;
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
        // Données des livraisons pour le modal
        const livraisonsData = <?= json_encode($livraisons) ?>;

        function editDelivery(livraisonId) {
            const livraison = livraisonsData.find(l => l.id == livraisonId);
            if (!livraison) return;

            document.getElementById('edit_livraison_id').value = livraisonId;
            document.getElementById('edit_date_prevue').value = livraison.date_prevue;
            document.getElementById('edit_date_reelle').value = livraison.date_reelle || '';
            document.getElementById('edit_transporteur').value = livraison.transporteur || '';
            document.getElementById('edit_numero_suivi').value = livraison.numero_suivi || '';
            document.getElementById('edit_statut').value = livraison.statut;
            document.getElementById('edit_notes').value = livraison.notes || '';

            document.getElementById('editDeliveryModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editDeliveryModal').style.display = 'none';
        }

        function copyTracking(trackingNumber) {
            navigator.clipboard.writeText(trackingNumber).then(() => {
                // Notification de copie
                const notification = document.createElement('div');
                notification.textContent = 'Numéro de suivi copié !';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #27ae60;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 6px;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 2000);
            });
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('editDeliveryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Fermer le modal avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });

        // Auto-update du statut basé sur les dates
        document.getElementById('edit_date_reelle').addEventListener('change', function() {
            const dateReelle = this.value;
            const statutSelect = document.getElementById('edit_statut');
            
            if (dateReelle) {
                statutSelect.value = 'livree';
            }
        });

        // Animation CSS pour la notification
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
                                