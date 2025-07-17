<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        switch ($action) {
            case 'supprimer':
                // Vérifier si la commande peut être supprimée
                $stmt = $manager->db->prepare("SELECT statut, numero_commande FROM commandes WHERE id = ?");
                $stmt->execute([$id]);
                $commande = $stmt->fetch();
                
                if (!$commande) {
                    throw new Exception("Commande non trouvée");
                }
                
                if (in_array($commande['statut'], ['expediee', 'livree'])) {
                    throw new Exception("Impossible de supprimer une commande expédiée ou livrée");
                }
                
                // Supprimer la commande et ses détails
                $manager->db->beginTransaction();
                
                $stmt = $manager->db->prepare("DELETE FROM details_commandes WHERE commande_id = ?");
                $stmt->execute([$id]);
                
                $stmt = $manager->db->prepare("UPDATE commandes SET statut = 'annulee' WHERE id = ?");
                $stmt->execute([$id]);
                
                $manager->db->commit();
                
                $message = "Commande {$commande['numero_commande']} annulée avec succès";
                break;
                
            case 'changer_statut':
                $nouveau_statut = $_POST['nouveau_statut'] ?? '';
                $statuts_valides = ['en_attente', 'confirmee', 'en_preparation', 'expediee', 'livree', 'annulee'];
                
                if (!in_array($nouveau_statut, $statuts_valides)) {
                    throw new Exception("Statut invalide");
                }
                
                $stmt = $manager->db->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
                $stmt->execute([$nouveau_statut, $id]);
                
                $message = "Statut de la commande mis à jour avec succès";
                break;
        }
        
    } catch (Exception $e) {
        if ($manager->db->inTransaction()) {
            $manager->db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Récupération des filtres
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

// Construction de la requête
$sql = "SELECT c.*, COUNT(dc.id) as nb_produits
        FROM commandes c
        LEFT JOIN details_commandes dc ON c.id = dc.commande_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (c.numero_commande LIKE ? OR c.client_nom LIKE ? OR c.client_email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($statut) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut;
}

if ($date_debut) {
    $sql .= " AND c.date_commande >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $sql .= " AND c.date_commande <= ?";
    $params[] = $date_fin;
}

$sql .= " GROUP BY c.id ORDER BY c.created_at DESC";

$stmt = $manager->db->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// Fonction pour formater les badges de statut
function getStatusBadge($statut) {
    $badges = [
        'en_attente' => '<span class="status alerte">En attente</span>',
        'confirmee' => '<span class="status actif">Confirmée</span>',
        'en_preparation' => '<span class="status actif">En préparation</span>',
        'expediee' => '<span class="status actif">Expédiée</span>',
        'livree' => '<span class="status actif">Livrée</span>',
        'annulee' => '<span class="status rupture">Annulée</span>'
    ];
    return $badges[$statut] ?? $statut;
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
                    <?= $search ? ' pour "' . htmlspecialchars($search) . '"' : '' ?>
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
                        <input type="text" name="search" placeholder="Rechercher (n° commande, client, email...)" 
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

                    <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" placeholder="Date début">
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" placeholder="Date fin">

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
                        <th>Produits</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($commandes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 50px; color: #666;">
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
                                        <?= date('d/m/Y', strtotime($commande['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($commande['client_nom']) ?></strong>
                                    <?php if ($commande['client_email']): ?>
                                        <br><small style="color: #666;">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($commande['client_email']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($commande['client_telephone']): ?>
                                        <br><small style="color: #666;">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($commande['client_telephone']) ?>
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
                                    <span style="font-weight: bold;"><?= $commande['nb_produits'] ?></span> produit(s)
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
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?= getStatusBadge($commande['statut']) ?>
                                        <?php if (in_array($commande['statut'], ['en_attente', 'confirmee', 'en_preparation'])): ?>
                                            <button onclick="changerStatut(<?= $commande['id'] ?>, '<?= $commande['statut'] ?>')" 
                                                    class="btn-border btn-small" 
                                                    style="padding: 2px 6px; font-size: 11px;" 
                                                    title="Changer le statut">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button onclick="voirDetails(<?= $commande['id'] ?>)" 
                                                class="btn-border btn-small" 
                                                title="Voir détails">
                                            <i class="fas fa-eye"></i> Détails
                                        </button>
                                        
                                        <?php if (in_array($commande['statut'], ['en_attente', 'confirmee'])): ?>
                                            <button onclick="modifierCommande(<?= $commande['id'] ?>)" 
                                                    class="btn-border btn-small" 
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($commande['statut'], ['confirmee', 'en_preparation'])): ?>
                                            <a href="../livraisons/nouvelle.php?commande_id=<?= $commande['id'] ?>" 
                                               class="btn-orange btn-small" 
                                               title="Planifier livraison">
                                                <i class="fas fa-truck"></i> Livraison
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($commande['statut'], ['en_attente', 'confirmee'])): ?>
                                            <button type="button" 
                                                    class="btn-danger btn-small" 
                                                    onclick="confirmerSuppression(<?= $commande['id'] ?>, '<?= htmlspecialchars($commande['numero_commande']) ?>')">
                                                <i class="fas fa-trash"></i> Annuler
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

    <!-- Modal de confirmation de suppression -->
    <div id="modalSuppression" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3 style="margin: 0; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Confirmer l'annulation</h3>
            </div>
            <div class="modal-body" style="margin-bottom: 30px;">
                <p>Êtes-vous sûr de vouloir annuler la commande "<span id="numeroCommande"></span>" ?</p>
                <p style="color: #666;"><strong>Cette action annulera définitivement la commande.</strong></p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-border" onclick="fermerModal()">Annuler</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="idCommande">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Annuler la commande
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal changement de statut -->
    <div id="modalStatut" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; max-width: 400px; width: 90%;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3 style="margin: 0; color: #ff6b35;"><i class="fas fa-edit"></i> Changer le statut</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="changer_statut">
                <input type="hidden" name="id" id="idCommandeStatut">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Nouveau statut :</label>
                    <select name="nouveau_statut" id="nouveauStatut" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" required>
                        <option value="en_attente">En attente</option>
                        <option value="confirmee">Confirmée</option>
                        <option value="en_preparation">En préparation</option>
                        <option value="expediee">Expédiée</option>
                        <option value="livree">Livrée</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-border" onclick="fermerModalStatut()">Annuler</button>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Modifier
                    </button>
                </div>
            </form>
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

        function changerStatut(id, statutActuel) {
            document.getElementById('idCommandeStatut').value = id;
            document.getElementById('nouveauStatut').value = statutActuel;
            document.getElementById('modalStatut').style.display = 'flex';
        }

        function fermerModalStatut() {
            document.getElementById('modalStatut').style.display = 'none';
        }

        function voirDetails(id) {
            // Pour l'instant, on affiche juste une alerte
            // Plus tard, vous pourrez créer une vraie page de détails
            alert('Fonction "Voir détails" à implémenter.\nCommande ID: ' + id);
        }

        function modifierCommande(id) {
            // Pour l'instant, on affiche juste une alerte
            // Plus tard, vous pourrez créer une vraie page de modification
            alert('Fonction "Modifier commande" à implémenter.\nCommande ID: ' + id);
        }

        // Fermer les modals en cliquant à l'extérieur
        document.getElementById('modalSuppression').addEventListener('click', function(e) {
            if (e.target === this) fermerModal();
        });

        document.getElementById('modalStatut').addEventListener('click', function(e) {
            if (e.target === this) fermerModalStatut();
        });
    </script>
</body>
</html>