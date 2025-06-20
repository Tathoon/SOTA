<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Commercial']);

$manager = new SotaManager();
$produits = $manager->getProduits();
$user = getCurrentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'client_nom' => trim($_POST['client_nom']),
            'client_email' => !empty($_POST['client_email']) ? trim($_POST['client_email']) : null,
            'client_telephone' => !empty($_POST['client_telephone']) ? trim($_POST['client_telephone']) : null,
            'client_adresse' => !empty($_POST['client_adresse']) ? trim($_POST['client_adresse']) : null,
            'date_commande' => $_POST['date_commande'] ?? date('Y-m-d'),
            'date_livraison_prevue' => !empty($_POST['date_livraison_prevue']) ? $_POST['date_livraison_prevue'] : null,
            'utilisateur_id' => $user['id'],
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
            $commandeId = $manager->creerCommande($data);
            
            if ($commandeId) {
                $message = "Commande créée avec succès (N° CMD-$commandeId)";
                $_POST = []; // Reset form
            } else {
                $error = "Erreur lors de la création de la commande";
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
    <title>Nouvelle commande - SOTA</title>
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
                <h2><i class="fas fa-plus-circle"></i> Nouvelle commande</h2>
                <p class="dashboard-subtitle">Création d'une commande client</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="order-form">
                <!-- Informations client -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Informations client
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_nom">Nom du client *</label>
                            <input type="text" name="client_nom" id="client_nom" required class="form-control" value="<?= htmlspecialchars($_POST['client_nom'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="client_email">Email</label>
                            <input type="email" name="client_email" id="client_email" class="form-control" value="<?= htmlspecialchars($_POST['client_email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="client_telephone">Téléphone</label>
                            <input type="tel" name="client_telephone" id="client_telephone" class="form-control" value="<?= htmlspecialchars($_POST['client_telephone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label for="client_adresse">Adresse de livraison</label>
                        <textarea name="client_adresse" id="client_adresse" rows="3" class="form-control"><?= htmlspecialchars($_POST['client_adresse'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Informations commande -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar"></i> Informations commande
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_commande">Date de commande</label>
                            <input type="date" name="date_commande" id="date_commande" value="<?= $_POST['date_commande'] ?? date('Y-m-d') ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="date_livraison_prevue">Date de livraison prévue</label>
                            <input type="date" name="date_livraison_prevue" id="date_livraison_prevue" class="form-control" value="<?= $_POST['date_livraison_prevue'] ?? '' ?>">
                        </div>
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
                        <i class="fas fa-save"></i> Créer la commande
                    </button>
                    <a href="commandes.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </main>
    </div>

    <script>
        let productCounter = 0;

        function ajouterProduit() {
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
                            <option value="<?= $p['id'] ?>" data-prix="<?= $p['prix_vente'] ?>" data-stock="<?= $p['stock_actuel'] ?>" <?= $p['stock_actuel'] == 0 ? 'disabled' : '' ?>>
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
                           onchange="updateSubtotal(${productCounter})" class="form-control" required>
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
                quantiteInput.max = stock;
                
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

        // Auto-complétion de la date de livraison
        document.getElementById('date_commande').addEventListener('change', function() {
            const dateCommande = new Date(this.value);
            const dateLivraison = new Date(dateCommande);
            dateLivraison.setDate(dateLivraison.getDate() + 7);
            
            document.getElementById('date_livraison_prevue').value = dateLivraison.toISOString().split('T')[0];
        });

        // Ajouter un produit par défaut
        document.addEventListener('DOMContentLoaded', function() {
            ajouterProduit();
        });
    </script>
</body>
</html>