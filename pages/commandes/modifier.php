<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$produits = $manager->getProduits();
$user = getCurrentUser();

$commande_id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

// Récupérer la commande à modifier
$commande = $manager->getDetailsCommande($commande_id);
if (!$commande) {
    header('Location: commandes.php');
    exit();
}

// Vérifier si la commande peut être modifiée
if (!$manager->commandePeutEtreModifiee($commande_id)) {
    $error = "Cette commande ne peut plus être modifiée (statut: " . $commande['statut'] . ")";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    try {
        $data = [
            'client_nom' => trim($_POST['client_nom']),
            'client_email' => !empty($_POST['client_email']) ? trim($_POST['client_email']) : null,
            'client_telephone' => !empty($_POST['client_telephone']) ? trim($_POST['client_telephone']) : null,
            'client_adresse' => !empty($_POST['client_adresse']) ? trim($_POST['client_adresse']) : null,
            'date_livraison_prevue' => !empty($_POST['date_livraison_prevue']) ? $_POST['date_livraison_prevue'] : null,
            'produits' => []
        ];
        
        // Validation
        $erreurs = validerDonneesCommande($data);
        
        // Traitement des produits
        if (!empty($_POST['produits'])) {
            foreach ($_POST['produits'] as $produitData) {
                if (!empty($produitData['produit_id']) && !empty($produitData['quantite'])) {
                    // Récupérer le prix du produit
                    foreach ($produits as $p) {
                        if ($p['id'] == $produitData['produit_id']) {
                            $data['produits'][] = [
                                'produit_id' => (int)$produitData['produit_id'],
                                'quantite' => (int)$produitData['quantite'],
                                'prix_unitaire' => (float)$p['prix_vente']
                            ];
                            break;
                        }
                    }
                }
            }
        }
        
        if (empty($data['produits'])) {
            $erreurs[] = "Aucun produit sélectionné";
        }
        
        if (!empty($erreurs)) {
            $error = implode('<br>', $erreurs);
        } else {
            if ($manager->modifierCommande($commande_id, $data, $user['id'])) {
                $message = "Commande modifiée avec succès";
                // Recharger la commande
                $commande = $manager->getDetailsCommande($commande_id);
            } else {
                $error = "Erreur lors de la modification de la commande";
            }
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
    <title>Modifier commande - SOTA</title>
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
                <h2><i class="fas fa-edit"></i> Modifier commande <?= htmlspecialchars($commande['numero_commande']) ?></h2>
                <p class="dashboard-subtitle">Modification d'une commande existante</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= $error ?></div>
            <?php endif; ?>

            <?php if (empty($error) || $manager->commandePeutEtreModifiee($commande_id)): ?>
            <form method="POST" class="order-form">
                <!-- Informations commande -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Informations commande
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Numéro de commande</label>
                            <input type="text" value="<?= htmlspecialchars($commande['numero_commande']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Date de commande</label>
                            <input type="text" value="<?= formatDate($commande['date_commande']) ?>" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Statut actuel</label>
                            <input type="text" value="<?= ucfirst($commande['statut']) ?>" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <!-- Informations client -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Informations client
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_nom">Nom du client *</label>
                            <input type="text" name="client_nom" id="client_nom" required class="form-control" 
                                   value="<?= htmlspecialchars($commande['client_nom']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="client_email">Email</label>
                            <input type="email" name="client_email" id="client_email" class="form-control" 
                                   value="<?= htmlspecialchars($commande['client_email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="client_telephone">Téléphone</label>
                            <input type="tel" name="client_telephone" id="client_telephone" class="form-control" 
                                   value="<?= htmlspecialchars($commande['client_telephone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="client_adresse">Adresse de livraison</label>
                        <textarea name="client_adresse" id="client_adresse" rows="3" class="form-control"><?= htmlspecialchars($commande['client_adresse'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Date de livraison -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar"></i> Livraison
                    </h3>
                    <div class="form-group">
                        <label for="date_livraison_prevue">Date de livraison prévue</label>
                        <input type="date" name="date_livraison_prevue" id="date_livraison_prevue" class="form-control" 
                               value="<?= $commande['date_livraison_prevue'] ?? '' ?>">
                    </div>
                </div>

                <!-- Produits -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-box"></i> Produits commandés
                    </h3>
                    
                    <div class="product-list" id="productList">
                        <!-- En-tête -->
                        <div class="product-header">
                            <div>Produit</div>
                            <div>Prix unitaire</div>
                            <div>Quantité</div>
                            <div>Sous-total</div>
                            <div>Action</div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-add-product" onclick="ajouterProduit()">
                        <i class="fas fa-plus"></i> Ajouter un produit
                    </button>
                    
                    <div class="total-section">
                        <div class="total-amount">
                            Total: <span id="totalCommande">0,00 €</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <a href="commandes.php" class="btn-border">
                        <i class="fas fa-arrow-left"></i> Retour aux commandes
                    </a>
                </div>
            </form>
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
        let productCounter = 0;
        
        // Produits existants de la commande
        const produitsExistants = <?= json_encode($commande['produits'] ?? []) ?>;

        function ajouterProduit(produitExistant = null) {
            productCounter++;
            const productList = document.getElementById('productList');
            
            const productItem = document.createElement('div');
            productItem.className = 'product-item';
            productItem.id = `product-${productCounter}`;
            
            productItem.innerHTML = `
                <div>
                    <select name="produits[${productCounter}][produit_id]" onchange="updatePrice(${productCounter})" class="form-control" required>
                        <option value="">Sélectionner un produit</option>
                        <?php foreach ($produits as $p): ?>
                            <option value="<?= $p['id'] ?>" data-prix="<?= $p['prix_vente'] ?>" data-stock="<?= $p['stock_actuel'] ?>" 
                                    ${produitExistant && produitExistant.produit_id == <?= $p['id'] ?> ? 'selected' : ''}>
                                <?= htmlspecialchars($p['reference'] . ' - ' . $p['nom']) ?> (Stock: <?= $p['stock_actuel'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <input type="text" id="prix-${productCounter}" class="form-control" readonly>
                </div>
                <div>
                    <input type="number" name="produits[${productCounter}][quantite]" 
                           id="quantite-${productCounter}" min="1" 
                           onchange="updateSubtotal(${productCounter})" class="form-control" required
                           value="${produitExistant ? produitExistant.quantite : ''}">
                </div>
                <div>
                    <input type="text" id="subtotal-${productCounter}" class="form-control" readonly>
                </div>
                <div>
                    <button type="button" class="btn-remove" onclick="supprimerProduit(${productCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            productList.appendChild(productItem);
            
            if (produitExistant) {
                updatePrice(productCounter);
            }
        }

        function supprimerProduit(counter) {
            const productItem = document.getElementById(`product-${counter}`);
            if (productItem) {
                productItem.remove();
                calculerTotal();
            }
        }

        function updatePrice(counter) {
            const select = document.querySelector(`select[name="produits[${counter}][produit_id]"]`);
            const prixInput = document.getElementById(`prix-${counter}`);
            const quantiteInput = document.getElementById(`quantite-${counter}`);
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const prix = parseFloat(option.dataset.prix);
                const stock = parseInt(option.dataset.stock);
                
                prixInput.value = prix.toFixed(2) + ' €';
                quantiteInput.max = stock + parseInt(quantiteInput.value || 0); // Stock + quantité actuelle
                
                updateSubtotal(counter);
            } else {
                prixInput.value = '';
                quantiteInput.removeAttribute('max');
                updateSubtotal(counter);
            }
        }

        function updateSubtotal(counter) {
            const select = document.querySelector(`select[name="produits[${counter}][produit_id]"]`);
            const quantite = parseInt(document.getElementById(`quantite-${counter}`).value) || 0;
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const prix = parseFloat(option.dataset.prix);
                const subtotal = prix * quantite;
                
                document.getElementById(`subtotal-${counter}`).value = subtotal.toFixed(2) + ' €';
            } else {
                document.getElementById(`subtotal-${counter}`).value = '';
            }
            
            calculerTotal();
        }

        function calculerTotal() {
            let total = 0;
            const subtotalInputs = document.querySelectorAll('[id^="subtotal-"]');
            
            subtotalInputs.forEach(input => {
                const value = parseFloat(input.value.replace(' €', '')) || 0;
                total += value;
            });
            
            document.getElementById('totalCommande').textContent = total.toFixed(2).replace('.', ',') + ' €';
        }

        // Charger les produits existants
        document.addEventListener('DOMContentLoaded', function() {
            if (produitsExistants.length > 0) {
                produitsExistants.forEach(produit => {
                    ajouterProduit(produit);
                });
            } else {
                ajouterProduit();
            }
        });
    </script>
</body>
</html>