<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$error = '';

// Récupération du fournisseur pré-sélectionné si fourni
$fournisseur_preselectionne = null;
if (!empty($_GET['fournisseur'])) {
    $fournisseur_preselectionne = $manager->getFournisseurById($_GET['fournisseur']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $data = [
            'fournisseur_id' => (int)$_POST['fournisseur_id'],
            'date_commande' => $_POST['date_commande'] ?: date('Y-m-d'),
            'date_livraison_prevue' => $_POST['date_livraison_prevue'] ?: null,
            'notes' => sanitizeInput($_POST['notes']),
            'utilisateur_id' => $user['id'],
            'produits' => []
        ];

        // Traitement des produits
        if (!empty($_POST['produits']) && is_array($_POST['produits'])) {
            foreach ($_POST['produits'] as $index => $produit_data) {
                if (!empty($produit_data['produit_id']) && !empty($produit_data['quantite'])) {
                    $data['produits'][] = [
                        'produit_id' => (int)$produit_data['produit_id'],
                        'quantite' => (int)$produit_data['quantite'],
                        'prix_unitaire' => (float)($produit_data['prix_unitaire'] ?: 0)
                    ];
                }
            }
        }

        // Validation
        if (empty($data['fournisseur_id'])) {
            throw new Exception("Veuillez sélectionner un fournisseur");
        }

        if (empty($data['produits'])) {
            throw new Exception("Au moins un produit doit être commandé");
        }

        $commande_id = $manager->creerCommandeFournisseur($data);
        
        if ($commande_id) {
            logActivite('creation_commande_fournisseur', [
                'commande_id' => $commande_id,
                'fournisseur_id' => $data['fournisseur_id'],
                'nb_produits' => count($data['produits'])
            ], $user['id']);
            
            header('Location: commandes_fournisseurs.php?message=' . urlencode('Commande fournisseur créée avec succès'));
            exit();
        } else {
            throw new Exception("Erreur lors de la création de la commande");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des données pour les formulaires
$fournisseurs = $manager->getFournisseurs();
$produits_disponibles = $manager->getProduits();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle commande fournisseur - SOTA Fashion</title>
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
                <h1><i class="fas fa-plus"></i> Nouvelle commande fournisseur</h1>
                <p class="dashboard-subtitle">Créer une commande d'approvisionnement</p>
            </section>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="order-form" id="orderForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <!-- Informations fournisseur -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-truck-loading"></i> Informations fournisseur
                    </h3>
                    
                    <div class="form-group">
                        <label for="fournisseur_id">Fournisseur *</label>
                        <select id="fournisseur_id" name="fournisseur_id" class="form-control" required onchange="updateFournisseurInfo()">
                            <option value="">Sélectionner un fournisseur</option>
                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                <option value="<?= $fournisseur['id'] ?>" 
                                        data-delais="<?= $fournisseur['delais_livraison'] ?>"
                                        data-conditions="<?= htmlspecialchars($fournisseur['conditions_paiement']) ?>"
                                        data-specialite="<?= htmlspecialchars($fournisseur['specialite_mode']) ?>"
                                        <?= $fournisseur_preselectionne && $fournisseur['id'] == $fournisseur_preselectionne['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fournisseur['nom']) ?> - <?= htmlspecialchars($fournisseur['ville']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Dates -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar"></i> Dates
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_commande">Date de commande</label>
                            <input type="date" id="date_commande" name="date_commande" class="form-control" 
                                   value="<?= $_POST['date_commande'] ?? date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_livraison_prevue">Date de livraison prévue</label>
                            <input type="date" id="date_livraison_prevue" name="date_livraison_prevue" class="form-control" 
                                   value="<?= $_POST['date_livraison_prevue'] ?? '' ?>">
                        </div>
                    </div>
                </div>

                <!-- Produits -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tshirt"></i> Produits à commander
                    </h3>
                    
                    <div id="produits-container">
                        <!-- Les lignes de produits seront ajoutées ici par JavaScript -->
                    </div>
                    
                    <button type="button" id="add-product" class="btn-add-product">
                        <i class="fas fa-plus"></i> Ajouter un produit
                    </button>
                </div>

                <!-- Notes -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-comment"></i> Notes et instructions
                    </h3>
                    <div class="form-group">
                        <label for="notes">Notes internes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                  placeholder="Instructions spéciales, conditions particulières..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Récapitulatif -->
                <div class="form-section">
                    <div class="total-section">
                        <div class="total-amount">
                            Total de la commande : <span id="total-commande">0,00 €</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="commandes_fournisseurs.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Créer la commande
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Données des produits disponibles
        const produitsDisponibles = <?= json_encode($produits_disponibles) ?>;
        let productCounter = 0;

        // Ajouter une ligne de produit
        function addProductLine() {
            const container = document.getElementById('produits-container');
            const lineId = 'product-line-' + productCounter++;
            
            const productLine = document.createElement('div');
            productLine.className = 'product-item';
            productLine.id = lineId;
            productLine.innerHTML = `
                <select name="produits[${productCounter}][produit_id]" class="form-control" required>
                    <option value="">Sélectionner un produit</option>
                    ${produitsDisponibles.map(p => 
                        `<option value="${p.id}">${p.nom} (${p.reference})</option>`
                    ).join('')}
                </select>
                <input type="number" name="produits[${productCounter}][quantite]" min="1" value="1" class="form-control" required>
                <input type="number" name="produits[${productCounter}][prix_unitaire]" step="0.01" min="0" class="form-control">
                <span class="sous-total">0,00 €</span>
                <button type="button" onclick="removeProductLine('${lineId}')" class="btn-remove">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            
            container.appendChild(productLine);
            calculateTotal();
        }

        // Supprimer une ligne de produit
        function removeProductLine(lineId) {
            const line = document.getElementById(lineId);
            if (line) {
                line.remove();
                calculateTotal();
            }
        }

        // Calculer le total
        function calculateTotal() {
            let total = 0;
            const productLines = document.querySelectorAll('.product-item');
            
            productLines.forEach(line => {
                const quantite = parseFloat(line.querySelector('input[name*="[quantite]"]').value) || 0;
                const prix = parseFloat(line.querySelector('input[name*="[prix_unitaire]"]').value) || 0;
                const sousTotal = quantite * prix;
                
                line.querySelector('.sous-total').textContent = sousTotal.toFixed(2) + ' €';
                total += sousTotal;
            });
            
            document.getElementById('total-commande').textContent = total.toFixed(2).replace('.', ',') + ' €';
        }

        function updateFournisseurInfo() {
            const select = document.getElementById('fournisseur_id');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                const delais = selectedOption.dataset.delais;
                const datePrevue = new Date();
                datePrevue.setDate(datePrevue.getDate() + parseInt(delais));
                
                document.getElementById('date_livraison_prevue').value = datePrevue.toISOString().split('T')[0];
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            addProductLine();
            
            document.getElementById('add-product').addEventListener('click', addProductLine);
            
            document.addEventListener('input', function(e) {
                if (e.target.matches('input[name*="[quantite]"]') || e.target.matches('input[name*="[prix_unitaire]"]')) {
                    calculateTotal();
                }
            });
            
            // Validation du formulaire
            document.getElementById('orderForm').addEventListener('submit', function(e) {
                const fournisseurId = document.getElementById('fournisseur_id').value;
                const productLines = document.querySelectorAll('.product-item');
                
                if (!fournisseurId) {
                    alert('Veuillez sélectionner un fournisseur');
                    e.preventDefault();
                    return;
                }
                
                let hasValidProduct = false;
                productLines.forEach(line => {
                    const produitId = line.querySelector('select').value;
                    const quantite = parseInt(line.querySelector('input[name*="[quantite]"]').value);
                    
                    if (produitId && quantite > 0) {
                        hasValidProduct = true;
                    }
                });
                
                if (!hasValidProduct) {
                    alert('Au moins un produit doit être commandé avec une quantité valide');
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>