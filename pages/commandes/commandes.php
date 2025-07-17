<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        if ($action === 'supprimer' && $id > 0) {
            // Vérifier si la commande existe et peut être supprimée
            $stmt = $manager->db->prepare("SELECT statut, numero_commande FROM commandes WHERE id = ?");
            $stmt->execute([$id]);
            $commande = $stmt->fetch();
            
            if ($commande) {
                if (in_array($commande['statut'], ['expediee', 'livree'])) {
                    $error = "Impossible de supprimer une commande expédiée ou livrée";
                } else {
                    // Marquer comme annulée
                    $stmt = $manager->db->prepare("UPDATE commandes SET statut = 'annulee' WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Commande {$commande['numero_commande']} annulée avec succès";
                }
            } else {
                $error = "Commande non trouvée";
            }
        }
        
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupération des filtres
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';

// Récupération des commandes
try {
    $sql = "SELECT c.* FROM commandes c WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (c.numero_commande LIKE ? OR c.client_nom LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($statut) {
        $sql .= " AND c.statut = ?";
        $params[] = $statut;
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $manager->db->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll();

} catch (Exception $e) {
    $commandes = [];
    $error = "Erreur lors du chargement des commandes";
}

// Fonction pour les badges de statut
function getStatusBadge($statut) {
    switch ($statut) {
        case 'en_attente':
            return '<span class="status alerte">En attente</span>';
        case 'confirmee':
            return '<span class="status actif">Confirmée</span>';
        case 'en_preparation':
            return '<span class="status actif">En préparation</span>';
        case 'expediee':
            return '<span class="status actif">Expédiée</span>';
        case 'livree':
            return '<span class="status actif">Livrée</span>';
        case 'annulee':
            return '<span class="status rupture">Annulée</span>';
        default:
            return '<span class="status">' . htmlspecialchars($statut) . '</span>';
    }
}
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
                <p class="dashboard-subtitle">
                    Suivez et gérez toutes vos commandes - <?= count($commandes) ?> commandes
                </p>
            </section>

            <?php if ($message): ?>
                <div class="dashboard-section" style="background: #d4edda; border-left: 4px solid #28a745; margin: 20px 30px;">
                    <p style="color: #155724; margin: 0;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="dashboard-section" style="background: #f8d7da; border-left: 4px solid #dc3545; margin: 20px 30px;">
                    <p style="color: #721c24; margin: 0;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- Filtres et recherche -->
            <section class="dashboard-section">
                <h2><i class="fas fa-filter"></i> Filtres et recherche</h2>
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher (n° commande, client...)" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <select name="statut">
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
                    <a href="nouvelle.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouvelle commande
                    </a>
                    <a href="../livraisons/livraisons.php" class="btn-border">
                        <i class="fas fa-truck"></i> Voir les livraisons
                    </a>
                </div>
            </section>

            <!-- Liste des commandes -->
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($commandes)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 50px; color: #666;">
                                <i class="fas fa-shopping-cart" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                                Aucune commande trouvée
                                <?php if (!$search && !$statut): ?>
                                    <br><a href="nouvelle.php" class="btn-orange" style="margin-top: 15px;">
                                        <i class="fas fa-plus"></i> Créer la première commande
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td>
                                    <strong style="color: #ff6b35;"><?= htmlspecialchars($commande['numero_commande']) ?></strong>
                                    <br><small style="color: #666;">
                                        <?= date('d/m/Y H:i', strtotime($commande['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($commande['client_nom']) ?></strong>
                                    <?php if ($commande['client_email']): ?>
                                        <br><small style="color: #666;">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($commande['client_email']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></strong>
                                    <?php if ($commande['date_livraison_prevue']): ?>
                                        <br><small style="color: #666;">
                                            <i class="fas fa-truck"></i> Livraison: <?= date('d/m/Y', strtotime($commande['date_livraison_prevue'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #ff6b35; font-size: 1.1em;">
                                        <?= number_format($commande['total'], 2) ?>€
                                    </strong>
                                    <?php if ($commande['total_ht']): ?>
                                        <br><small style="color: #666;">
                                            HT: <?= number_format($commande['total_ht'], 2) ?>€
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= getStatusBadge($commande['statut']) ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button onclick="alert('Détails de la commande <?= htmlspecialchars($commande['numero_commande']) ?>\n\nClient: <?= htmlspecialchars($commande['client_nom']) ?>\nTotal: <?= number_format($commande['total'], 2) ?>€\nStatut: <?= htmlspecialchars($commande['statut']) ?>')" 
                                                class="btn-border btn-small" 
                                                title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if (in_array($commande['statut'], ['confirmee', 'en_preparation'])): ?>
                                            <a href="../livraisons/nouvelle.php?commande_id=<?= $commande['id'] ?>" 
                                               class="btn-orange btn-small" 
                                               title="Planifier livraison">
                                                <i class="fas fa-truck"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($commande['statut'], ['en_attente', 'confirmee'])): ?>
                                            <button type="button" 
                                                    class="btn-danger btn-small" 
                                                    onclick="confirmerSuppression(<?= $commande['id'] ?>, '<?= htmlspecialchars($commande['numero_commande']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

    <!-- Modal de confirmation simple -->
    <div id="modalSuppression" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 400px; width: 90%; text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: #e74c3c;">
                <i class="fas fa-exclamation-triangle"></i> Confirmer l'annulation
            </h3>
            <p>Voulez-vous vraiment annuler cette commande ?</p>
            <p><strong id="numeroCommande"></strong></p>
            
            <div style="margin-top: 20px;">
                <button type="button" class="btn-border" onclick="fermerModal()">Annuler</button>
                <form method="POST" style="display: inline; margin-left: 10px;">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="idCommande">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Confirmer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmerSuppression(id, numero) {
            document.getElementById('idCommande').value = id;
            document.getElementById('numeroCommande').textContent = numero;
            document.getElementById('modalSuppression').style.display = 'flex';
        }

        function fermerModal() {
            document.getElementById('modalSuppression').style.display = 'none';
        }

        // Fermer en cliquant à l'extérieur
        document.getElementById('modalSuppression').addEventListener('click', function(e) {
            if (e.target === this) {
                fermerModal();
            }
        });
    </script>
</body>
</html>