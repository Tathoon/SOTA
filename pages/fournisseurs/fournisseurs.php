<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    try {
        $id = (int)$_POST['id'];
        
        // Vérifier si le fournisseur a des commandes en cours
        $stmt = $manager->db->prepare("
            SELECT COUNT(*) FROM commandes_fournisseurs 
            WHERE fournisseur_id = ? AND statut NOT IN ('annulee', 'recue')
        ");
        $stmt->execute([$id]);
        $commandes_actives = $stmt->fetchColumn();
        
        if ($commandes_actives > 0) {
            throw new Exception("Impossible de supprimer ce fournisseur car il a $commandes_actives commande(s) en cours");
        }
        
        // Récupérer le nom pour le message
        $stmt = $manager->db->prepare("SELECT nom FROM fournisseurs WHERE id = ?");
        $stmt->execute([$id]);
        $nom_fournisseur = $stmt->fetchColumn();
        
        // Marquer comme inactif au lieu de supprimer
        $stmt = $manager->db->prepare("UPDATE fournisseurs SET actif = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = "Fournisseur '$nom_fournisseur' supprimé avec succès";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des filtres
$search = $_GET['search'] ?? '';

// Récupération des fournisseurs
$fournisseurs = $manager->getFournisseurs($search);
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
                <p class="dashboard-subtitle">
                    Gérez vos partenaires fournisseurs - <?= count($fournisseurs) ?> fournisseurs actifs
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
                <h2><i class="fas fa-filter"></i> Recherche</h2>
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
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Nom du fournisseur</th>
                        <th>Contact</th>
                        <th>Téléphone / Email</th>
                        <th>Ville</th>
                        <th>Délai livraison</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fournisseurs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 50px; color: #666;">
                                <i class="fas fa-truck-loading" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
                                Aucun fournisseur trouvé
                                <?php if (!$search): ?>
                                    <br><a href="nouveau.php" class="btn-orange" style="margin-top: 15px;">
                                        <i class="fas fa-plus"></i> Ajouter le premier fournisseur
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fournisseurs as $fournisseur): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($fournisseur['nom']) ?></strong>
                                    <?php if ($fournisseur['specialite_mode']): ?>
                                        <br><small style="color: #666;"><?= htmlspecialchars($fournisseur['specialite_mode']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($fournisseur['contact'] ?? 'Non défini') ?>
                                    <?php if ($fournisseur['siret']): ?>
                                        <br><small style="color: #666;">SIRET: <?= htmlspecialchars($fournisseur['siret']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fournisseur['telephone']): ?>
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($fournisseur['telephone']) ?><br>
                                    <?php endif; ?>
                                    <?php if ($fournisseur['email']): ?>
                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($fournisseur['email']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fournisseur['ville']): ?>
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($fournisseur['ville']) ?>
                                        <?php if ($fournisseur['code_postal']): ?>
                                            <br><small style="color: #666;"><?= htmlspecialchars($fournisseur['code_postal']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $fournisseur['delais_livraison'] ?> jours
                                    <?php if ($fournisseur['conditions_paiement']): ?>
                                        <br><small style="color: #666;"><?= htmlspecialchars($fournisseur['conditions_paiement']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="commandes_fournisseurs.php?fournisseur=<?= $fournisseur['id'] ?>" 
                                           class="btn-orange btn-small">
                                            <i class="fas fa-shopping-cart"></i> Commandes
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn-danger btn-small" 
                                                onclick="confirmerSuppression(<?= $fournisseur['id'] ?>, '<?= htmlspecialchars($fournisseur['nom']) ?>')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
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
                <h3 style="margin: 0; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Confirmer la suppression</h3>
            </div>
            <div class="modal-body" style="margin-bottom: 30px;">
                <p>Êtes-vous sûr de vouloir supprimer le fournisseur "<span id="nomFournisseur"></span>" ?</p>
                <p style="color: #666;"><strong>Cette action est irréversible.</strong></p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-border" onclick="fermerModal()">Annuler</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" id="idFournisseur">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Supprimer définitivement
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmerSuppression(id, nom) {
            document.getElementById('idFournisseur').value = id;
            document.getElementById('nomFournisseur').textContent = nom;
            document.getElementById('modalSuppression').style.display = 'flex';
        }

        function fermerModal() {
            document.getElementById('modalSuppression').style.display = 'none';
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('modalSuppression').addEventListener('click', function(e) {
            if (e.target === this) {
                fermerModal();
            }
        });
    </script>
</body>
</html>