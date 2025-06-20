<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$user = getCurrentUser();

$commande_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

// Récupérer la commande à supprimer
$commande = $manager->getDetailsCommande($commande_id);
if (!$commande) {
    header('Location: commandes.php');
    exit();
}

// Vérifier si la commande peut être supprimée
if (!$manager->commandePeutEtreSupprimee($commande_id)) {
    $error = "Cette commande ne peut plus être supprimée (statut: " . $commande['statut'] . ")";
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'confirmer' && empty($error)) {
    try {
        if ($manager->supprimerCommande($commande_id, $user['id'])) {
            $_SESSION['success'] = "Commande " . $commande['numero_commande'] . " supprimée avec succès";
            header('Location: commandes.php');
            exit();
        } else {
            $error = "Erreur lors de la suppression de la commande";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer commande - SOTA</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container stock-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="main-content stock-main">
            <div class="top-bar">
                <a href="../../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h2><i class="fas fa-trash-alt"></i> Supprimer commande <?= htmlspecialchars($commande['numero_commande']) ?></h2>
                <p class="dashboard-subtitle">Confirmation de suppression</p>
            </section>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($error)): ?>
            <div class="order-form">
                <div class="form-section">
                    <h3 class="section-title" style="color: #e74c3c;">
                        <i class="fas fa-exclamation-triangle"></i> Attention - Suppression définitive
                    </h3>
                    
                    <div style="background: #fff3cd; padding: 20px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #f39c12;">
                        <p><strong>Êtes-vous sûr de vouloir supprimer cette commande ?</strong></p>
                        <p>Cette action est <strong>irréversible</strong> et aura les conséquences suivantes :</p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <?php if (in_array($commande['statut'], ['confirmee', 'en_preparation'])): ?>
                                <li>Les produits seront <strong>remis en stock</strong></li>
                            <?php endif; ?>
                            <li>Toutes les données de la commande seront <strong>perdues</strong></li>
                            <li>L'historique des mouvements de stock sera conservé</li>
                        </ul>
                    </div>

                    <!-- Détails de la commande -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Numéro de commande</label>
                            <input type="text" value="<?= htmlspecialchars($commande['numero_commande']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Client</label>
                            <input type="text" value="<?= htmlspecialchars($commande['client_nom']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Date de commande</label>
                            <input type="text" value="<?= formatDate($commande['date_commande']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Statut</label>
                            <input type="text" value="<?= ucfirst($commande['statut']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Total</label>
                            <input type="text" value="<?= formatPrice($commande['total']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Nb produits</label>
                            <input type="text" value="<?= count($commande['produits'] ?? []) ?>" class="form-control" readonly>
                        </div>
                    </div>

                    <!-- Liste des produits -->
                    <?php if (!empty($commande['produits'])): ?>
                    <h4 style="margin: 20px 0 10px 0; color: var(--secondary-color);">Produits de la commande :</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <?php foreach ($commande['produits'] as $produit): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e9ecef;">
                            <span><strong><?= htmlspecialchars($produit['reference']) ?></strong> - <?= htmlspecialchars($produit['produit_nom']) ?></span>
                            <span>Qté: <?= $produit['quantite'] ?> × <?= formatPrice($produit['prix_unitaire']) ?> = <?= formatPrice($produit['sous_total']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <form method="POST" style="display: inline;" onsubmit="return confirmerSuppression()">
                        <input type="hidden" name="action" value="confirmer">
                        <button type="submit" class="btn-danger">
                            <i class="fas fa-trash-alt"></i> Confirmer la suppression
                        </button>
                    </form>
                    <a href="commandes.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </div>
            <?php else: ?>
                <div class="form-actions" style="margin: 30px;">
                    <a href="commandes.php" class="btn-border">
                        <i class="fas fa-arrow-left"></i> Retour aux commandes
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function confirmerSuppression() {
        return confirm('ATTENTION : Cette action est irréversible !\n\nÊtes-vous absolument certain de vouloir supprimer la commande <?= htmlspecialchars($commande['numero_commande']) ?> ?\n\nTapez "SUPPRIMER" pour confirmer :') && 
               prompt('Tapez "SUPPRIMER" en majuscules pour confirmer :') === 'SUPPRIMER';
    }
    </script>
</body>
</html>