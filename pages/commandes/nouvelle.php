<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$user = getCurrentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        // Récupération et nettoyage des données
        $data = [
            'client_nom' => sanitizeInput($_POST['client_nom']),
            'client_email' => sanitizeInput($_POST['client_email']),
            'client_telephone' => sanitizeInput($_POST['client_telephone']),
            'client_adresse' => sanitizeInput($_POST['client_adresse']),
            'client_code_postal' => sanitizeInput($_POST['client_code_postal']),
            'client_ville' => sanitizeInput($_POST['client_ville']),
            'date_commande' => $_POST['date_commande'] ?: date('Y-m-d'),
            'date_livraison_prevue' => $_POST['date_livraison_prevue'] ?: null,
            'utilisateur_id' => $user['id'],
            'produits' => []
        ];

        // Traitement des produits
        if (!empty($_POST['produits']) && is_array($_POST['produits'])) {
            foreach ($_POST['produits'] as $index => $produit_data) {
                if (!empty($produit_data['produit_id']) && !empty($produit_data['quantite'])) {
                    // Récupération du prix du produit
                    $produit_info = $manager->getProduitById($produit_data['produit_id']);
                    if (!$produit_info) {
                        throw new Exception("Produit non trouvé (ligne " . ($index + 1) . ")");
                    }

                    $data['produits'][] = [
                        'produit_id' => (int)$produit_data['produit_id'],
                        'quantite' => (int)$produit_data['quantite'],
                        'prix_unitaire' => (float)($produit_data['prix_unitaire'] ?: $produit_info['prix_vente']),
                        'taux_tva' => 20.00
                    ];
                }
            }
        }

        // Validation
        if (empty($data['client_nom'])) {
            throw new Exception("Le nom du client est obligatoire");
        }

        if (empty($data['produits'])) {
            throw new Exception("Au moins un produit doit être commandé");
        }

        $commande_id = $manager->creerCommande($data);
        
        if ($commande_id) {
            logActivite('creation_commande', [
                'commande_id' => $commande_id,
                'client_nom' => $data['client_nom'],
                'nb_produits' => count($data['produits'])
            ], $user['id']);
            
            header('Location: commandes.php?message=' . urlencode('Commande créée avec succès'));
            exit();
        } else {
            throw new Exception("Erreur lors de la création de la commande");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des produits disponibles
$produits_disponibles = $manager->getProduits();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle commande - SOTA Fashion</title>
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
                <h1><i class="fas fa-plus"></i> Nouvelle commande</h1>
                <p class="dashboard-subtitle">Créer une nouvelle commande client</p>
            </section>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="order-form" id="orderForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <!-- Informations client -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Informations client
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_nom">Nom du client *</label>
                            <input type="text" id="client_nom" name="client_nom" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['client_nom'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="client_email">Email</label>
                            <input type="email" id="client_email" name="client_email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['client_email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="client_telephone">Téléphone</label>
                            <input type="tel" id="client_telephone" name="client_telephone" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['client_telephone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_adresse">Adresse de livraison</label>
                        <textarea id="client_adresse" name="client_adresse" class="form-control" rows="2"><?= htmlspecialchars($_POST['client_adresse'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_code_postal">Code postal</label>
                            <input type="text" id="client_code_postal" name="client_code_postal" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['client_code_postal'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="client_ville">Ville</label>
                            <input type="text" id="client_ville" name="client_ville" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['client_ville'] ?? '') ?>">
                        </div>
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
                        <i class="fas fa-tshirt"></i> Produits commandés
                    </h3>
                    
                    <div id="produits-container">
                        <!-- Les lignes de produits seront ajoutées ici par JavaScript -->
                    </div>
                    
                    <button type="button" id="add-product" class="btn-add-product">
                        <i class="fas fa-plus"></i> Ajouter un produit
                    </button>
                </div>

                <!-- Récapitulatif -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calculator"></i> Récapitulatif
                    </h3>
                    <div class="total-section">
                        <div class="total-row">
                            <span>Sous-total HT :</span>
                            <span id="total-ht">0,00 €</span>
                        </div>
                        <div class="total-row">
                            <span>TVA (20%) :</span>
                            <span id="total-tva">0,00 €</span>
                        </div>
                        <div class="total-row total-final">
                            <span>Total TTC :</span>
                            <span id="total-ttc">0,00 €</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="commandes.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Créer la commande
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .order-form {
            background: white;
            margin: 0 30px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .form-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 14px;
        }

        .form-control {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .product-line {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .product-line select,
        .product-line input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-remove {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-remove:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        .btn-add-product {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-add-product:hover {
            background: #219a52;
            transform: translateY(-1px);
        }

        .total-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .total-row:last-child {
            border-bottom: none;
        }

        .total-final {
            font-weight: 600;
            font-size: 16px;
            color: var(--primary-color);
            border-top: 2px solid var(--primary-color);
            margin-top: 10px;
            padding-top: 15px;
        }

        .form-actions {
            padding: 25px 30px;
            background: #f8f9fa;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #eee;
        }

        .product-info {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
    </style>

    <script>
        // Données des produits disponibles
        const produitsDisponibles = <?= json_encode($produits_disponibles) ?>;
        let productCounter = 0;

        // Ajouter une ligne de produit
        function addProductLine() {
            const container = document.getElementById('produits-container');
            const lineId = 'product-line-' + productCounter++;
            
            const productLine = document.createElement('div');
            productLine.className = 'product-line';
            productLine.id = lineId;
            productLine.innerHTML = `
                <div class="form-group">
                    <label>Produit</label>
                    <select name="produits[${productCounter}][produit_id]" class="product-select" required onchange="updateProductInfo(this, '${lineId}')">
                        <option value="">Sélectionner un produit</option>
                        ${produitsDisponibles.map(p => 
                            `<option value="${p.id}" data-prix="${p.prix_vente}" data-stock="${p.stock_actuel}" data-nom="${p.nom}" data-reference="${p.reference}">
                                ${p.nom} (${p.reference}) - ${p.prix_vente}€ - Stock: ${p.stock_actuel}
                            </option>`
                        ).join('')}
                    </select>
                    <div class="product-info" id="info-${lineId}"></div>
                </div>
                <div class="form-group">
                    <label>Quantité</label>
                    <input type="number" name="produits[${productCounter}][quantite]" min="1" value="1" required onchange="calculateTotals()">
                </div>
                <div class="form-group">
                    <label>Prix unitaire (€)</label>
                    <input type="number" name="produits[${productCounter}][prix_unitaire]" step="0.01" min="0" onchange="calculateTotals()">
                </div>
                <div class="form-group">
                    <label>Sous-total</label>
                    <input type="text" class="sous-total" readonly>
                </div>
                <button type="button" onclick="removeProductLine('${lineId}')" class="btn-remove" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            
            container.appendChild(productLine);
            calculateTotals();
        }

        // Supprimer une ligne de produit
        function removeProductLine(lineId) {
            const line = document.getElementById(lineId);
            if (line) {
                line.remove();
                calculateTotals();
            }
        }

        // Mettre à jour les informations du produit
        function updateProductInfo(select, lineId) {
            const selectedOption = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('info-' + lineId);
            const prixInput = select.closest('.product-line').querySelector('input[name*="[prix_unitaire]"]');
            
            if (selectedOption.value) {
                const prix = selectedOption.dataset.prix;
                const stock = selectedOption.dataset.stock;
                const nom = selectedOption.dataset.nom;
                
                prixInput.value = prix;
                infoDiv.innerHTML = `Stock disponible: ${stock} | Prix: ${prix}€`;
                
                // Vérifier le stock
                if (parseInt(stock) === 0) {
                    infoDiv.innerHTML += ' <span style="color: #e74c3c; font-weight: 600;">RUPTURE</span>';
                } else if (parseInt(stock) <= 5) {
                    infoDiv.innerHTML += ' <span style="color: #f39c12; font-weight: 600;">STOCK FAIBLE</span>';
                }
            } else {
                prixInput.value = '';
                infoDiv.innerHTML = '';
            }
            
            calculateTotals();
        }

        // Calculer les totaux
        function calculateTotals() {
            let totalHT = 0;
            
            const productLines = document.querySelectorAll('.product-line');
            productLines.forEach(line => {
                const quantite = parseFloat(line.querySelector('input[name*="[quantite]"]').value) || 0;
                const prixUnitaire = parseFloat(line.querySelector('input[name*="[prix_unitaire]"]').value) || 0;
                const sousTotal = quantite * prixUnitaire;
                
                line.querySelector('.sous-total').value = sousTotal.toFixed(2) + ' €';
                totalHT += sousTotal;
            });
            
            const tva = totalHT * 0.20;
            const totalTTC = totalHT + tva;
            
            document.getElementById('total-ht').textContent = totalHT.toFixed(2).replace('.', ',') + ' €';
            document.getElementById('total-tva').textContent = tva.toFixed(2).replace('.', ',') + ' €';
            document.getElementById('total-ttc').textContent = totalTTC.toFixed(2).replace('.', ',') + ' €';
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter la première ligne de produit
            addProductLine();
            
            // Bouton d'ajout de produit
            document.getElementById('add-product').addEventListener('click', addProductLine);
            
            // Validation du formulaire
            document.getElementById('orderForm').addEventListener('submit', function(e) {
                const clientNom = document.getElementById('client_nom').value.trim();
                const productLines = document.querySelectorAll('.product-line');
                
                if (!clientNom) {
                    alert('Le nom du client est obligatoire');
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
                
                // Vérification des stocks
                let stockError = false;
                productLines.forEach(line => {
                    const select = line.querySelector('select');
                    const selectedOption = select.options[select.selectedIndex];
                    const quantite = parseInt(line.querySelector('input[name*="[quantite]"]').value) || 0;
                    
                    if (selectedOption.value && quantite > 0) {
                        const stock = parseInt(selectedOption.dataset.stock);
                        if (quantite > stock) {
                            alert(`Stock insuffisant pour ${selectedOption.dataset.nom}. Stock disponible: ${stock}`);
                            stockError = true;
                        }
                    }
                });
                
                if (stockError) {
                    e.preventDefault();
                    return;
                }
            });
            
            // Calcul automatique des totaux lors des changements
            document.addEventListener('input', function(e) {
                if (e.target.matches('input[name*="[quantite]"]') || e.target.matches('input[name*="[prix_unitaire]"]')) {
                    calculateTotals();
                }
            });
        });
    </script>
</body>
</html>