<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Seuls Admin et Commercial peuvent accéder à cette page
requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$commandes = $manager->getCommandes();
$user = getCurrentUser();

$message = '';
$error = '';

// Données d'exemple si aucune commande en base
if (empty($commandes)) {
    $commandes = [
        [
            'id' => 1,
            'numero_commande' => 'CMD-2025-001',
            'client_nom' => 'Dupont Pierre',
            'date_commande' => '2025-06-01',
            'statut' => 'en_attente',
            'total' => 150.00,
            'nb_produits' => 3
        ],
        [
            'id' => 2,
            'numero_commande' => 'CMD-2025-002',
            'client_nom' => 'Martin Sophie',
            'date_commande' => '2025-05-30',
            'statut' => 'confirmee',
            'total' => 75.50,
            'nb_produits' => 2
        ]
    ];
}

// Traitement du changement de statut
if ($_POST['action'] ?? '' === 'changer_statut') {
    $commande_id = (int)$_POST['commande_id'];
    $nouveau_statut = $_POST['nouveau_statut'];
    
    if ($manager->mettreAJourStatutCommande($commande_id, $nouveau_statut)) {
        $message = "Statut mis à jour avec succès";
        // Recharger les commandes
        $commandes = $manager->getCommandes();
    } else {
        $error = "Erreur lors de la mise à jour du statut";
    }
}

// Afficher les messages de session
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des commandes - SOTA</title>
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
                <h2><i class="fas fa-shopping-cart"></i> Gestion des commandes</h2>
                <p class="dashboard-subtitle">Suivi et gestion des commandes clients</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Nb produits</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $commande): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($commande['numero_commande'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($commande['client_nom'] ?? '') ?></td>
                        <td><?= formatDate($commande['date_commande'] ?? date('Y-m-d')) ?></td>
                        <td><?= $commande['nb_produits'] ?? 0 ?></td>
                        <td><?= formatPrice($commande['total'] ?? 0) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="changer_statut">
                                <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                                <select name="nouveau_statut" onchange="this.form.submit()" class="status-select">
                                    <option value="en_attente" <?= ($commande['statut'] ?? '') === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="confirmee" <?= ($commande['statut'] ?? '') === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                                    <option value="en_preparation" <?= ($commande['statut'] ?? '') === 'en_preparation' ? 'selected' : '' ?>>En préparation</option>
                                    <option value="expediee" <?= ($commande['statut'] ?? '') === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                                    <option value="livree" <?= ($commande['statut'] ?? '') === 'livree' ? 'selected' : '' ?>>Livrée</option>
                                    <option value="annulee" <?= ($commande['statut'] ?? '') === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                </select>
                            </form>
                        </td>
                        <td class="actions">
                            <a href="details.php?id=<?= $commande['id'] ?>" class="btn-border btn-small" title="Voir les détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php 
                            // Vérifier si la commande peut être modifiée
                            $peutModifier = $manager->commandePeutEtreModifiee($commande['id']);
                            $peutSupprimer = $manager->commandePeutEtreSupprimee($commande['id']);
                            ?>
                            
                            <?php if ($peutModifier): ?>
                                <a href="modifier.php?id=<?= $commande['id'] ?>" class="btn-border btn-small" title="Modifier la commande">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php else: ?>
                                <span class="btn-border btn-small" style="opacity: 0.5; cursor: not-allowed;" title="Modification impossible (<?= $commande['statut'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($peutSupprimer): ?>
                                <a href="supprimer.php?id=<?= $commande['id'] ?>" class="btn-danger btn-small" 
                                   title="Supprimer la commande" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <span class="btn-danger btn-small" style="opacity: 0.5; cursor: not-allowed;" title="Suppression impossible (<?= $commande['statut'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="stock-actions">
                <a href="nouvelle.php" class="btn-orange">
                    <i class="fas fa-plus"></i> Nouvelle commande
                </a>
                <button onclick="filtrerCommandes()" class="btn-border">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <button onclick="exporterCommandes()" class="btn-border">
                    <i class="fas fa-download"></i> Exporter
                </button>
                <button onclick="afficherStatistiques()" class="btn-border">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </button>
            </div>

            <!-- Zone de statistiques (masquée par défaut) -->
            <div id="statistiques" class="dashboard-section" style="display: none;">
                <h3><i class="fas fa-chart-pie"></i> Statistiques des commandes</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3 id="total-commandes"><?= count($commandes) ?></h3>
                            <p>Total commandes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3 id="ca-total"><?= formatPrice(array_sum(array_column($commandes, 'total'))) ?></h3>
                            <p>Chiffre d'affaires</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-content">
                            <h3 id="panier-moyen">
                                <?= count($commandes) > 0 ? formatPrice(array_sum(array_column($commandes, 'total')) / count($commandes)) : '0,00 €' ?>
                            </h3>
                            <p>Panier moyen</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
    .status-select {
        padding: 4px 8px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 12px;
        background: white;
        cursor: pointer;
    }

    .status-select:focus {
        outline: none;
        border-color: var(--primary-color);
    }

    .actions {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    .btn-small {
        padding: 6px 8px;
        font-size: 11px;
        min-width: 30px;
        text-align: center;
    }
    </style>

    <script>
    function filtrerCommandes() {
        // Simulation d'un filtre
        const statut = prompt('Filtrer par statut (en_attente, confirmee, expediee, livree) :');
        if (statut) {
            window.location.href = `commandes.php?filtre_statut=${encodeURIComponent(statut)}`;
        }
    }

    function exporterCommandes() {
        // Simulation d'export
        alert('Export des commandes en cours...\nFonctionnalité à développer : génération fichier CSV/Excel');
    }

    function afficherStatistiques() {
        const statsDiv = document.getElementById('statistiques');
        if (statsDiv.style.display === 'none') {
            statsDiv.style.display = 'block';
            statsDiv.scrollIntoView({ behavior: 'smooth' });
        } else {
            statsDiv.style.display = 'none';
        }
    }

    // Confirmation pour les changements de statut critiques
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function(e) {
            if (this.value === 'annulee') {
                if (!confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
                    e.preventDefault();
                    // Restaurer la valeur précédente
                    this.value = this.defaultValue;
                    return false;
                }
            }
            if (this.value === 'livree') {
                if (!confirm('Confirmer la livraison de cette commande ?')) {
                    e.preventDefault();
                    this.value = this.defaultValue;
                    return false;
                }
            }
        });
        
        // Sauvegarder la valeur par défaut
        select.defaultValue = select.value;
    });
    </script>
</body>
</html>