<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Préparateur']);

$manager = new SotaManager();
$produits = $manager->getProduits();
$user = getCurrentUser();

$message = '';
$error = '';

// Pré-sélection d'un produit si passé en paramètre
$produit_preselect = $_GET['produit'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $produit_id = (int)$_POST['produit_id'];
        $type_mouvement = $_POST['type_mouvement'];
        $quantite = (int)$_POST['quantite'];
        $motif = $_POST['motif'] ?? '';
        
        if ($manager->ajouterMouvementStock($produit_id, $type_mouvement, $quantite, $motif, $user['id'])) {
            $message = "Mouvement de stock enregistré avec succès";
            $_POST = []; // Reset form
        } else {
            $error = "Erreur lors de l'enregistrement";
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
    <title>Mouvement de stock - SOTA</title>
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
                <h2><i class="fas fa-exchange-alt"></i> Mouvement de stock</h2>
                <p class="dashboard-subtitle">Entrée ou sortie de produits</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="stock-form">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-box"></i> Informations du mouvement
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="produit_id">Produit *</label>
                            <select name="produit_id" id="produit_id" required class="form-control">
                                <option value="">Sélectionner un produit</option>
                                <?php foreach ($produits as $produit): ?>
                                    <option value="<?= $produit['id'] ?>" 
                                            data-stock="<?= $produit['stock_actuel'] ?>"
                                            <?= $produit_preselect == $produit['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($produit['reference'] . ' - ' . $produit['nom']) ?>
                                        (Stock: <?= $produit['stock_actuel'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="type_mouvement">Type de mouvement *</label>
                            <select name="type_mouvement" id="type_mouvement" required class="form-control">
                                <option value="">Sélectionner</option>
                                <option value="entree">Entrée (réception)</option>
                                <option value="sortie">Sortie (expédition)</option>
                                <option value="ajustement">Ajustement d'inventaire</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quantite">Quantité *</label>
                            <input type="number" name="quantite" id="quantite" min="1" required class="form-control">
                            <small id="stock-info" style="color: #666;"></small>
                        </div>

                        <div class="form-group">
                            <label for="motif">Motif</label>
                            <input type="text" name="motif" id="motif" placeholder="Raison du mouvement" class="form-control" value="<?= htmlspecialchars($_POST['motif'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Enregistrer le mouvement
                    </button>
                    <a href="stocks.php" class="btn-border">
                        <i class="fas fa-arrow-left"></i> Retour aux stocks
                    </a>
                </div>
            </form>
        </main>
    </div>

    <script>
    // Validation côté client
    document.getElementById('type_mouvement').addEventListener('change', updateStockInfo);
    document.getElementById('produit_id').addEventListener('change', updateStockInfo);

    function updateStockInfo() {
        const quantiteInput = document.getElementById('quantite');
        const produitSelect = document.getElementById('produit_id');
        const typeMouvement = document.getElementById('type_mouvement').value;
        const selectedOption = produitSelect.options[produitSelect.selectedIndex];
        const stockInfo = document.getElementById('stock-info');
        
        if (selectedOption && selectedOption.value) {
            const stockActuel = parseInt(selectedOption.dataset.stock);
            
            if (typeMouvement === 'sortie') {
                quantiteInput.setAttribute('max', stockActuel);
                stockInfo.textContent = `Stock disponible: ${stockActuel}`;
                stockInfo.style.color = stockActuel > 0 ? '#27ae60' : '#e74c3c';
            } else {
                quantiteInput.removeAttribute('max');
                stockInfo.textContent = `Stock actuel: ${stockActuel}`;
                stockInfo.style.color = '#666';
            }
        } else {
            quantiteInput.removeAttribute('max');
            stockInfo.textContent = '';
        }
    }

    // Auto-remplir le motif selon le type
    document.getElementById('type_mouvement').addEventListener('change', function() {
        const motifInput = document.getElementById('motif');
        if (!motifInput.value) {
            switch(this.value) {
                case 'entree':
                    motifInput.value = 'Réception fournisseur';
                    break;
                case 'sortie':
                    motifInput.value = 'Expédition commande';
                    break;
                case 'ajustement':
                    motifInput.value = 'Ajustement inventaire';
                    break;
            }
        }
    });

    // Initialiser si un produit est pré-sélectionné
    window.addEventListener('load', updateStockInfo);
    </script>
</body>
</html>