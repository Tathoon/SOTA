<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Préparateur']);

$manager = new SotaManager();
$user = getCurrentUser();

$error = '';
$success = '';

// Récupération du produit pré-sélectionné si fourni
$produit_preselectionne = null;
if (!empty($_GET['produit'])) {
    $produit_preselectionne = $manager->getProduitById($_GET['produit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $produit_id = (int)$_POST['produit_id'];
        $type_mouvement = sanitizeInput($_POST['type_mouvement']);
        $quantite = (int)$_POST['quantite'];
        $motif = sanitizeInput($_POST['motif']);
        $reference_document = sanitizeInput($_POST['reference_document']);
        $cout_unitaire = !empty($_POST['cout_unitaire']) ? (float)$_POST['cout_unitaire'] : null;

        // Validation
        if (empty($produit_id)) {
            throw new Exception("Veuillez sélectionner un produit");
        }

        if (empty($type_mouvement) || !in_array($type_mouvement, ['entree', 'sortie', 'ajustement'])) {
            throw new Exception("Type de mouvement invalide");
        }

        if (empty($quantite) || $quantite <= 0) {
            throw new Exception("La quantité doit être supérieure à 0");
        }

        // Pour l'ajustement, la quantité représente le nouveau stock
        if ($type_mouvement === 'ajustement') {
            $produit_actuel = $manager->getProduitById($produit_id);
            if (!$produit_actuel) {
                throw new Exception("Produit non trouvé");
            }
            
            $ancienne_quantite = $produit_actuel['stock_actuel'];
            $nouvelle_quantite = $quantite;
            $quantite_mouvement = abs($nouvelle_quantite - $ancienne_quantite);
            
            if ($nouvelle_quantite == $ancienne_quantite) {
                throw new Exception("Le nouveau stock est identique au stock actuel");
            }
            
            $motif = $motif ?: "Ajustement de stock : $ancienne_quantite → $nouvelle_quantite";
        } else {
            $quantite_mouvement = $quantite;
        }

        // Ajout du mouvement
        $result = $manager->ajouterMouvementStock(
            $produit_id,
            $type_mouvement,
            $type_mouvement === 'ajustement' ? $quantite : $quantite_mouvement,
            $motif,
            $user['id'],
            $reference_document
        );

        if ($result) {
            logActivite('mouvement_stock', [
                'produit_id' => $produit_id,
                'type_mouvement' => $type_mouvement,
                'quantite' => $quantite_mouvement,
                'motif' => $motif
            ], $user['id']);

            $success = "Mouvement de stock enregistré avec succès";
            
            // Redirection après succès pour éviter la re-soumission
            $redirect_url = "mouvement.php?success=" . urlencode($success);
            if ($produit_preselectionne) {
                $redirect_url .= "&produit=" . $produit_preselectionne['id'];
            }
            header("Location: $redirect_url");
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des produits pour la liste déroulante
$produits = $manager->getProduits();

// Message de succès de la redirection
if (!empty($_GET['success'])) {
    $success = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvement de stock - SOTA Fashion</title>
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
                <h1><i class="fas fa-exchange-alt"></i> Mouvement de stock</h1>
                <p class="dashboard-subtitle">Enregistrer une entrée, sortie ou ajustement de stock</p>
            </section>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="movement-container">
                <!-- Formulaire de mouvement -->
                <div class="movement-form-section">
                    <form method="POST" class="movement-form" id="movementForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- Sélection du produit -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-tshirt"></i> Sélection du produit
                            </h3>
                            
                            <div class="form-group">
                                <label for="produit_id">Produit *</label>
                                <select id="produit_id" name="produit_id" class="form-control" required onchange="updateProductInfo()">
                                    <option value="">Sélectionner un produit</option>
                                    <?php foreach ($produits as $produit): ?>
                                        <option value="<?= $produit['id'] ?>" 
                                                data-stock="<?= $produit['stock_actuel'] ?>"
                                                data-seuil="<?= $produit['seuil_minimum'] ?>"
                                                data-nom="<?= htmlspecialchars($produit['nom']) ?>"
                                                data-reference="<?= htmlspecialchars($produit['reference']) ?>"
                                                data-emplacement="<?= htmlspecialchars($produit['emplacement']) ?>"
                                                data-statut="<?= $produit['statut_stock'] ?>"
                                                <?= $produit_preselectionne && $produit['id'] == $produit_preselectionne['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($produit['nom']) ?> (<?= htmlspecialchars($produit['reference']) ?>) - Stock: <?= $produit['stock_actuel'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Informations du produit sélectionné -->
                            <div id="product-info" class="product-info-card" style="display: none;">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Stock actuel</label>
                                        <span id="info-stock" class="info-value"></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Seuil minimum</label>
                                        <span id="info-seuil" class="info-value"></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Emplacement</label>
                                        <span id="info-emplacement" class="info-value"></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Statut</label>
                                        <span id="info-statut" class="info-value"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Type de mouvement -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-cogs"></i> Type de mouvement
                            </h3>
                            
                            <div class="movement-types">
                                <label class="movement-type-card">
                                    <input type="radio" name="type_mouvement" value="entree" required onchange="updateQuantityLabel()">
                                    <div class="card-content">
                                        <i class="fas fa-plus-circle"></i>
                                        <h4>Entrée de stock</h4>
                                        <p>Réapprovisionnement, livraison fournisseur</p>
                                    </div>
                                </label>
                                
                                <label class="movement-type-card">
                                    <input type="radio" name="type_mouvement" value="sortie" required onchange="updateQuantityLabel()">
                                    <div class="card-content">
                                        <i class="fas fa-minus-circle"></i>
                                        <h4>Sortie de stock</h4>
                                        <p>Vente, casse, perte, retour</p>
                                    </div>
                                </label>
                                
                                <label class="movement-type-card">
                                    <input type="radio" name="type_mouvement" value="ajustement" required onchange="updateQuantityLabel()">
                                    <div class="card-content">
                                        <i class="fas fa-balance-scale"></i>
                                        <h4>Ajustement</h4>
                                        <p>Correction d'inventaire, réajustement</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Quantité et détails -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-hashtag"></i> Quantité et détails
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="quantite" id="quantity-label">Quantité *</label>
                                    <input type="number" id="quantite" name="quantite" class="form-control" 
                                           min="1" value="1" required onchange="calculateNewStock()">
                                    <small id="quantity-help" class="form-help"></small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cout_unitaire">Coût unitaire (€)</label>
                                    <input type="number" id="cout_unitaire" name="cout_unitaire" class="form-control" 
                                           step="0.01" min="0" placeholder="Optionnel">
                                    <small class="form-help">Coût d'achat pour valoriser le stock</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="motif">Motif</label>
                                <textarea id="motif" name="motif" class="form-control" rows="2" 
                                          placeholder="Raison du mouvement de stock..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="reference_document">Référence document</label>
                                <input type="text" id="reference_document" name="reference_document" class="form-control" 
                                       placeholder="N° commande, facture, bon de livraison...">
                            </div>
                            
                            <!-- Aperçu du nouveau stock -->
                            <div id="stock-preview" class="stock-preview" style="display: none;">
                                <div class="preview-content">
                                    <span>Nouveau stock après mouvement :</span>
                                    <span id="new-stock-value" class="new-stock-value"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="form-actions">
                            <a href="stocks.php" class="btn-border">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn-orange">
                                <i class="fas fa-save"></i> Enregistrer le mouvement
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Historique récent -->
                <div class="recent-movements-section">
                    <h3 class="section-title">
                        <i class="fas fa-history"></i> Mouvements récents
                    </h3>
                    
                    <div class="recent-movements">
                        <?php
                        $mouvements_recents = $manager->getHistoriqueMouvements(
                            $produit_preselectionne ? $produit_preselectionne['id'] : null, 
                            10
                        );
                        
                        if (empty($mouvements_recents)): ?>
                            <div class="empty-movements">
                                <i class="fas fa-history"></i>
                                <p>Aucun mouvement récent</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mouvements_recents as $mouvement): ?>
                                <div class="movement-item <?= $mouvement['type_mouvement'] ?>">
                                    <div class="movement-icon">
                                        <?php if ($mouvement['type_mouvement'] === 'entree'): ?>
                                            <i class="fas fa-plus-circle"></i>
                                        <?php elseif ($mouvement['type_mouvement'] === 'sortie'): ?>
                                            <i class="fas fa-minus-circle"></i>
                                        <?php else: ?>
                                            <i class="fas fa-balance-scale"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="movement-info">
                                        <strong><?= htmlspecialchars($mouvement['produit_nom']) ?></strong>
                                        <small><?= htmlspecialchars($mouvement['produit_reference']) ?></small>
                                        <div class="movement-details">
                                            <span class="movement-type"><?= ucfirst($mouvement['type_mouvement']) ?></span>
                                            <span class="movement-quantity">
                                                <?= $mouvement['type_mouvement'] === 'entree' ? '+' : ($mouvement['type_mouvement'] === 'sortie' ? '-' : '±') ?><?= $mouvement['quantite'] ?>
                                            </span>
                                            <span class="movement-date"><?= formatDateTime($mouvement['date_mouvement']) ?></span>
                                        </div>
                                        <?php if ($mouvement['motif']): ?>
                                            <small class="movement-reason"><?= htmlspecialchars($mouvement['motif']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="movement-result">
                                        <span class="stock-change"><?= $mouvement['quantite_avant'] ?> → <?= $mouvement['quantite_apres'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="view-all-movements">
                                <a href="historique.php<?= $produit_preselectionne ? '?produit=' . $produit_preselectionne['id'] : '' ?>" class="btn-border btn-small">
                                    <i class="fas fa-list"></i> Voir tout l'historique
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .movement-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin: 0 30px;
        }

        .movement-form-section,
        .recent-movements-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .movement-form {
            padding: 0;
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

        .form-help {
            margin-top: 5px;
            color: #666;
            font-size: 12px;
            font-style: italic;
        }

        .product-info-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-item label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            margin: 0;
        }

        .info-value {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .movement-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .movement-type-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .movement-type-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.1);
        }

        .movement-type-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .movement-type-card input[type="radio"]:checked + .card-content {
            background: rgba(255, 107, 53, 0.1);
        }

        .movement-type-card input[type="radio"]:checked ~ .card-content h4 {
            color: var(--primary-color);
        }

        .card-content {
            text-align: center;
            transition: all 0.3s ease;
        }

        .card-content i {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .card-content h4 {
            margin: 0 0 8px 0;
            color: var(--secondary-color);
            font-size: 16px;
        }

        .card-content p {
            margin: 0;
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }

        .stock-preview {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .preview-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        .new-stock-value {
            font-size: 18px;
            color: var(--success-color);
        }

        .new-stock-value.warning {
            color: var(--warning-color);
        }

        .new-stock-value.danger {
            color: var(--danger-color);
        }

        .form-actions {
            padding: 25px 30px;
            background: #f8f9fa;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #eee;
        }

        .recent-movements-section {
            padding: 30px;
        }

        .recent-movements {
            max-height: 600px;
            overflow-y: auto;
        }

        .empty-movements {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-movements i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .movement-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .movement-item:hover {
            background: #f8f9fa;
        }

        .movement-item.entree {
            border-left-color: var(--success-color);
            background: rgba(39, 174, 96, 0.05);
        }

        .movement-item.sortie {
            border-left-color: var(--danger-color);
            background: rgba(231, 76, 60, 0.05);
        }

        .movement-item.ajustement {
            border-left-color: var(--info-color);
            background: rgba(52, 152, 219, 0.05);
        }

        .movement-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .movement-item.entree .movement-icon {
            background: var(--success-color);
        }

        .movement-item.sortie .movement-icon {
            background: var(--danger-color);
        }

        .movement-item.ajustement .movement-icon {
            background: var(--info-color);
        }

        .movement-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .movement-info strong {
            color: var(--secondary-color);
            font-size: 14px;
        }

        .movement-info small {
            color: #666;
            font-size: 12px;
        }

        .movement-details {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .movement-type {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .movement-quantity {
            font-weight: 600;
            font-size: 13px;
        }

        .movement-date {
            color: #666;
            font-size: 11px;
        }

        .movement-reason {
            color: #666;
            font-style: italic;
            font-size: 12px;
        }

        .movement-result {
            text-align: right;
        }

        .stock-change {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 13px;
        }

        .view-all-movements {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #eee;
            margin-top: 15px;
        }

        @media (max-width: 1200px) {
            .movement-container {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function updateProductInfo() {
            const select = document.getElementById('produit_id');
            const selectedOption = select.options[select.selectedIndex];
            const infoCard = document.getElementById('product-info');
            
            if (selectedOption.value) {
                const stock = selectedOption.dataset.stock;
                const seuil = selectedOption.dataset.seuil;
                const emplacement = selectedOption.dataset.emplacement || 'Non défini';
                const statut = selectedOption.dataset.statut;
                
                document.getElementById('info-stock').textContent = stock + ' unités';
                document.getElementById('info-seuil').textContent = seuil;
                document.getElementById('info-emplacement').textContent = emplacement;
                
                // Statut avec badge
                const statutSpan = document.getElementById('info-statut');
                statutSpan.innerHTML = getStatusBadge(statut);
                
                infoCard.style.display = 'block';
                
                // Mettre à jour l'aperçu du stock
                calculateNewStock();
            } else {
                infoCard.style.display = 'none';
                document.getElementById('stock-preview').style.display = 'none';
            }
        }

        function updateQuantityLabel() {
            const typeRadios = document.getElementsByName('type_mouvement');
            const quantityLabel = document.getElementById('quantity-label');
            const quantityHelp = document.getElementById('quantity-help');
            
            let selectedType = '';
            for (const radio of typeRadios) {
                if (radio.checked) {
                    selectedType = radio.value;
                    break;
                }
            }
            
            switch (selectedType) {
                case 'entree':
                    quantityLabel.textContent = 'Quantité à ajouter *';
                    quantityHelp.textContent = 'Nombre d\'unités à ajouter au stock';
                    break;
                case 'sortie':
                    quantityLabel.textContent = 'Quantité à retirer *';
                    quantityHelp.textContent = 'Nombre d\'unités à retirer du stock';
                    break;
                case 'ajustement':
                    quantityLabel.textContent = 'Nouveau stock *';
                    quantityHelp.textContent = 'Nouveau niveau de stock après ajustement';
                    break;
                default:
                    quantityLabel.textContent = 'Quantité *';
                    quantityHelp.textContent = '';
            }
            
            calculateNewStock();
        }

        function calculateNewStock() {
            const select = document.getElementById('produit_id');
            const selectedOption = select.options[select.selectedIndex];
            const quantityInput = document.getElementById('quantite');
            const stockPreview = document.getElementById('stock-preview');
            const newStockValue = document.getElementById('new-stock-value');
            
            if (!selectedOption.value || !quantityInput.value) {
                stockPreview.style.display = 'none';
                return;
            }
            
            const currentStock = parseInt(selectedOption.dataset.stock);
            const seuil = parseInt(selectedOption.dataset.seuil);
            const quantity = parseInt(quantityInput.value);
            
            const typeRadios = document.getElementsByName('type_mouvement');
            let selectedType = '';
            for (const radio of typeRadios) {
                if (radio.checked) {
                    selectedType = radio.value;
                    break;
                }
            }
            
            let newStock = currentStock;
            
            switch (selectedType) {
                case 'entree':
                    newStock = currentStock + quantity;
                    break;
                case 'sortie':
                    newStock = currentStock - quantity;
                    break;
                case 'ajustement':
                    newStock = quantity;
                    break;
            }
            
            // Afficher le nouveau stock
            newStockValue.textContent = newStock + ' unités';
            
            // Appliquer les classes de couleur selon le niveau
            newStockValue.className = 'new-stock-value';
            if (newStock < 0) {
                newStockValue.className += ' danger';
                newStockValue.textContent += ' (ATTENTION: Stock négatif!)';
            } else if (newStock === 0) {
                newStockValue.className += ' danger';
                newStockValue.textContent += ' (Rupture de stock)';
            } else if (newStock <= seuil) {
                newStockValue.className += ' warning';
                newStockValue.textContent += ' (Stock critique)';
            }
            
            stockPreview.style.display = 'block';
        }

        function getStatusBadge(status) {
            const badges = {
                'normal': '<span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">Normal</span>',
                'alerte': '<span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">Alerte</span>',
                'rupture': '<span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">Rupture</span>'
            };
            return badges[status] || status;
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser l'affichage si un produit est pré-sélectionné
            updateProductInfo();
            
            // Event listeners pour les changements
            document.getElementById('produit_id').addEventListener('change', updateProductInfo);
            document.getElementById('quantite').addEventListener('input', calculateNewStock);
            
            // Event listeners pour les types de mouvement
            const typeRadios = document.getElementsByName('type_mouvement');
            typeRadios.forEach(radio => {
                radio.addEventListener('change', updateQuantityLabel);
            });
            
            // Validation du formulaire
            document.getElementById('movementForm').addEventListener('submit', function(e) {
                const produitId = document.getElementById('produit_id').value;
                const quantite = parseInt(document.getElementById('quantite').value);
                
                if (!produitId) {
                    alert('Veuillez sélectionner un produit');
                    e.preventDefault();
                    return;
                }
                
                if (!quantite || quantite <= 0) {
                    alert('La quantité doit être supérieure à 0');
                    e.preventDefault();
                    return;
                }
                
                // Vérification pour les sorties
                const selectedOption = document.getElementById('produit_id').options[document.getElementById('produit_id').selectedIndex];
                const currentStock = parseInt(selectedOption.dataset.stock);
                
                const typeRadios = document.getElementsByName('type_mouvement');
                let selectedType = '';
                for (const radio of typeRadios) {
                    if (radio.checked) {
                        selectedType = radio.value;
                        break;
                    }
                }
                
                if (selectedType === 'sortie' && quantite > currentStock) {
                    if (!confirm(`Attention: Vous tentez de retirer ${quantite} unités mais le stock actuel n'est que de ${currentStock}.\n\nContinuer quand même ?`)) {
                        e.preventDefault();
                        return;
                    }
                }
                
                if (selectedType === 'ajustement') {
                    const newStock = quantite;
                    if (newStock < 0) {
                        alert('Le nouveau stock ne peut pas être négatif');
                        e.preventDefault();
                        return;
                    }
                }
            });
        });
    </script>
</body>
</html>