<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$categories = $manager->getCategories();
$user = getCurrentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'reference' => trim($_POST['reference']),
            'nom' => trim($_POST['nom']),
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
            'categorie_id' => !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null,
            'stock_actuel' => (int)($_POST['stock_actuel'] ?? 0),
            'seuil_minimum' => (int)($_POST['seuil_minimum'] ?? 5),
            'prix_achat' => !empty($_POST['prix_achat']) ? (float)$_POST['prix_achat'] : null,
            'prix_vente' => (float)$_POST['prix_vente'],
            'taille' => !empty($_POST['taille']) ? trim($_POST['taille']) : null,
            'couleur' => !empty($_POST['couleur']) ? trim($_POST['couleur']) : null,
            'emplacement' => !empty($_POST['emplacement']) ? trim($_POST['emplacement']) : null
        ];
        
        if (empty($data['reference']) || empty($data['nom']) || empty($data['prix_vente'])) {
            throw new Exception("Tous les champs obligatoires doivent être remplis");
        }
        
        if ($data['prix_vente'] <= 0) {
            throw new Exception("Le prix de vente doit être supérieur à 0");
        }
        
        if ($manager->ajouterProduit($data)) {
            $message = "Produit ajouté avec succès";
            $_POST = []; // Reset form
        } else {
            $error = "Erreur lors de l'ajout du produit (référence peut-être déjà existante)";
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
    <title>Nouveau produit - SOTA</title>
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
                <h2><i class="fas fa-plus-circle"></i> Nouveau produit</h2>
                <p class="dashboard-subtitle">Ajout d'un produit à l'inventaire</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="product-form">
                <!-- Informations de base -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Informations de base
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="couleur">Couleur</label>
                            <input type="text" name="couleur" id="couleur" class="form-control" 
                                   placeholder="Rouge, Bleu, Noir..." value="<?= htmlspecialchars($_POST['couleur'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="emplacement">Emplacement</label>
                            <input type="text" name="emplacement" id="emplacement" class="form-control" 
                                   placeholder="A1, B2, Entrepôt..." value="<?= htmlspecialchars($_POST['emplacement'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Ajouter le produit
                    </button>
                    <a href="produits.php" class="btn-border">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            </form>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-génération de référence
        const nomInput = document.getElementById('nom');
        const referenceInput = document.getElementById('reference');
        
        nomInput.addEventListener('blur', function() {
            if (!referenceInput.value && this.value) {
                // Générer une référence basée sur le nom
                const nom = this.value.toUpperCase()
                    .replace(/[^A-Z0-9]/g, '')
                    .substring(0, 4);
                const numero = String(Date.now()).slice(-3);
                referenceInput.value = `${nom}-${numero}`;
            }
        });

        // Calcul de marge automatique
        const prixAchatInput = document.getElementById('prix_achat');
        const prixVenteInput = document.getElementById('prix_vente');
        
        function calculerMarge() {
            const achat = parseFloat(prixAchatInput.value) || 0;
            const vente = parseFloat(prixVenteInput.value) || 0;
            
            if (achat > 0 && vente > 0) {
                const marge = ((vente - achat) / achat * 100).toFixed(1);
                const margeInfo = document.getElementById('marge-info');
                if (!margeInfo) {
                    const info = document.createElement('small');
                    info.id = 'marge-info';
                    info.style.color = marge >= 20 ? '#27ae60' : '#f39c12';
                    prixVenteInput.parentNode.appendChild(info);
                }
                document.getElementById('marge-info').textContent = `Marge: ${marge}%`;
                document.getElementById('marge-info').style.color = marge >= 20 ? '#27ae60' : '#f39c12';
            }
        }

        prixAchatInput.addEventListener('input', calculerMarge);
        prixVenteInput.addEventListener('input', calculerMarge);

        // Validation en temps réel
        const form = document.querySelector('.product-form');
        form.addEventListener('submit', function(e) {
            const reference = referenceInput.value.trim();
            const nom = nomInput.value.trim();
            const prix = parseFloat(prixVenteInput.value);

            if (!reference || !nom || !prix || prix <= 0) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires avec des valeurs valides.');
                return false;
            }

            // Confirmer l'ajout
            if (!confirm(`Ajouter le produit "${nom}" avec la référence "${reference}" ?`)) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
</body>
</html>
                            <label for="reference">Référence *</label>
                            <input type="text" name="reference" id="reference" required class="form-control" 
                                   placeholder="PROD-001" value="<?= htmlspecialchars($_POST['reference'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="nom">Nom du produit *</label>
                            <input type="text" name="nom" id="nom" required class="form-control" 
                                   placeholder="Nom du produit" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="categorie_id">Catégorie</label>
                            <select name="categorie_id" id="categorie_id" class="form-control">
                                <option value="">Sans catégorie</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>" <?= ($_POST['categorie_id'] ?? '') == $categorie['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categorie['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" rows="3" class="form-control" 
                                  placeholder="Description détaillée du produit"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Prix et stock -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-euro-sign"></i> Prix et stock
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prix_achat">Prix d'achat</label>
                            <input type="number" name="prix_achat" id="prix_achat" step="0.01" min="0" class="form-control" 
                                   placeholder="0.00" value="<?= $_POST['prix_achat'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="prix_vente">Prix de vente *</label>
                            <input type="number" name="prix_vente" id="prix_vente" step="0.01" min="0.01" required class="form-control" 
                                   placeholder="0.00" value="<?= $_POST['prix_vente'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="stock_actuel">Stock initial</label>
                            <input type="number" name="stock_actuel" id="stock_actuel" min="0" class="form-control" 
                                   value="<?= $_POST['stock_actuel'] ?? 0 ?>">
                        </div>

                        <div class="form-group">
                            <label for="seuil_minimum">Seuil d'alerte</label>
                            <input type="number" name="seuil_minimum" id="seuil_minimum" min="0" class="form-control" 
                                   value="<?= $_POST['seuil_minimum'] ?? 5 ?>">
                            <small>Quantité en dessous de laquelle une alerte sera affichée</small>
                        </div>
                    </div>
                </div>

                <!-- Caractéristiques -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tags"></i> Caractéristiques
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="taille">Taille</label>
                            <input type="text" name="taille" id="taille" class="form-control" 
                                   placeholder="S, M, L, XL..." value="<?= htmlspecialchars($_POST['taille'] ?? '') ?>">
                        </div>

                        <div class="form-group">