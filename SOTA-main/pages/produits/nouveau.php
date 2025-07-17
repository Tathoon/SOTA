<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération et nettoyage des données
        $data = [
            'reference' => sanitizeInput($_POST['reference']),
            'nom' => sanitizeInput($_POST['nom']),
            'description' => sanitizeInput($_POST['description']),
            'categorie_id' => !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null,
            'stock_actuel' => (int)($_POST['stock_actuel'] ?? 0),
            'seuil_minimum' => (int)($_POST['seuil_minimum'] ?? 5),
            'prix_achat' => !empty($_POST['prix_achat']) ? (float)$_POST['prix_achat'] : null,
            'prix_vente' => (float)$_POST['prix_vente'],
            'taille' => sanitizeInput($_POST['taille']),
            'couleur' => sanitizeInput($_POST['couleur']),
            'emplacement' => sanitizeInput($_POST['emplacement']),
            'lot_minimum' => (int)($_POST['lot_minimum'] ?? 1),
            'poids' => !empty($_POST['poids']) ? (float)$_POST['poids'] : null,
            'composition' => sanitizeInput($_POST['composition']),
            'saison' => sanitizeInput($_POST['saison']),
            'marque' => sanitizeInput($_POST['marque']),
            'collection' => sanitizeInput($_POST['collection']),
            'utilisateur_id' => $user['id']
        ];

        // Validation
        if (empty($data['reference'])) {
            throw new Exception("La référence est obligatoire");
        }
        if (empty($data['nom'])) {
            throw new Exception("Le nom du lot est obligatoire");
        }
        if (empty($data['prix_vente']) || $data['prix_vente'] <= 0) {
            throw new Exception("Le prix de vente doit être supérieur à 0");
        }

        $produit_id = $manager->ajouterProduit($data);
        
        if ($produit_id) {
            logActivite('creation_produit', [
                'produit_id' => $produit_id,
                'reference' => $data['reference'],
                'nom' => $data['nom']
            ], $user['id']);
            
            header('Location: produits.php?message=' . urlencode('Lot créé avec succès'));
            exit();
        } else {
            throw new Exception("Erreur lors de la création du lot");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$categories = $manager->getCategories();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau lot - SOTA Fashion</title>
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
                <h1><i class="fas fa-plus"></i> Nouveau lot</h1>
                <p class="dashboard-subtitle">Ajouter un nouvel article au catalogue</p>
            </section>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="product-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <!-- Informations générales -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Informations générales
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="reference">Référence *</label>
                            <input type="text" id="reference" name="reference" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['reference'] ?? generateReference('PROD')) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nom">Nom du lot *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="marque">Marque</label>
                            <input type="text" id="marque" name="marque" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['marque'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="collection">Collection</label>
                            <input type="text" id="collection" name="collection" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['collection'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Classification -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tags"></i> Classification
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="categorie_id">Catégorie</label>
                            <select id="categorie_id" name="categorie_id" class="form-control">
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($_POST['categorie_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="taille">Taille</label>
                            <select id="taille" name="taille" class="form-control">
                                <option value="">Sélectionner une taille</option>
                                <option value="XS" <?= ($_POST['taille'] ?? '') === 'XS' ? 'selected' : '' ?>>XS</option>
                                <option value="S" <?= ($_POST['taille'] ?? '') === 'S' ? 'selected' : '' ?>>S</option>
                                <option value="M" <?= ($_POST['taille'] ?? '') === 'M' ? 'selected' : '' ?>>M</option>
                                <option value="L" <?= ($_POST['taille'] ?? '') === 'L' ? 'selected' : '' ?>>L</option>
                                <option value="XL" <?= ($_POST['taille'] ?? '') === 'XL' ? 'selected' : '' ?>>XL</option>
                                <option value="XXL" <?= ($_POST['taille'] ?? '') === 'XXL' ? 'selected' : '' ?>>XXL</option>
                                <option value="34" <?= ($_POST['taille'] ?? '') === '34' ? 'selected' : '' ?>>34</option>
                                <option value="36" <?= ($_POST['taille'] ?? '') === '36' ? 'selected' : '' ?>>36</option>
                                <option value="38" <?= ($_POST['taille'] ?? '') === '38' ? 'selected' : '' ?>>38</option>
                                <option value="40" <?= ($_POST['taille'] ?? '') === '40' ? 'selected' : '' ?>>40</option>
                                <option value="42" <?= ($_POST['taille'] ?? '') === '42' ? 'selected' : '' ?>>42</option>
                                <option value="44" <?= ($_POST['taille'] ?? '') === '44' ? 'selected' : '' ?>>44</option>
                                <option value="46" <?= ($_POST['taille'] ?? '') === '46' ? 'selected' : '' ?>>46</option>
                                <option value="Unique" <?= ($_POST['taille'] ?? '') === 'Unique' ? 'selected' : '' ?>>Taille unique</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="couleur">Couleur</label>
                            <input type="text" id="couleur" name="couleur" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['couleur'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="saison">Saison</label>
                            <select id="saison" name="saison" class="form-control">
                                <option value="">Sélectionner une saison</option>
                                <option value="Printemps/Été" <?= ($_POST['saison'] ?? '') === 'Printemps/Été' ? 'selected' : '' ?>>Printemps/Été</option>
                                <option value="Automne/Hiver" <?= ($_POST['saison'] ?? '') === 'Automne/Hiver' ? 'selected' : '' ?>>Automne/Hiver</option>
                                <option value="Toute saison" <?= ($_POST['saison'] ?? '') === 'Toute saison' ? 'selected' : '' ?>>Toute saison</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Prix et stock -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-euro-sign"></i> Prix et stock
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prix_achat">Prix d'achat (€)</label>
                            <input type="number" id="prix_achat" name="prix_achat" class="form-control" 
                                   step="0.01" min="0" value="<?= $_POST['prix_achat'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="prix_vente">Prix de vente (€) *</label>
                            <input type="number" id="prix_vente" name="prix_vente" class="form-control" 
                                   step="0.01" min="0.01" value="<?= $_POST['prix_vente'] ?? '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock_actuel">Stock initial</label>
                            <input type="number" id="stock_actuel" name="stock_actuel" class="form-control" 
                                   min="0" value="<?= $_POST['stock_actuel'] ?? '0' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="seuil_minimum">Seuil d'alerte</label>
                            <input type="number" id="seuil_minimum" name="seuil_minimum" class="form-control" 
                                   min="0" value="<?= $_POST['seuil_minimum'] ?? '5' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="lot_minimum">Lot minimum</label>
                            <input type="number" id="lot_minimum" name="lot_minimum" class="form-control" 
                                   min="1" value="<?= $_POST['lot_minimum'] ?? '1' ?>">
                        </div>
                    </div>
                </div>

                <!-- Détails techniques -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-cogs"></i> Détails techniques
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="composition">Composition</label>
                            <textarea id="composition" name="composition" class="form-control" rows="2" 
                                      placeholder="Ex: 100% Coton, 95% Viscose 5% Elasthanne..."><?= htmlspecialchars($_POST['composition'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="poids">Poids (g)</label>
                            <input type="number" id="poids" name="poids" class="form-control" 
                                   step="0.1" min="0" value="<?= $_POST['poids'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emplacement">Emplacement</label>
                            <input type="text" id="emplacement" name="emplacement" class="form-control" 
                                   placeholder="Ex: A1-B2, Rayon 3-Étagère 2..." 
                                   value="<?= htmlspecialchars($_POST['emplacement'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="produits.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Créer le lot
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .product-form {
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

        .form-actions {
            padding: 25px 30px;
            background: #f8f9fa;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #eee;
        }

        .marge-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 13px;
        }

        .marge-positive {
            color: #27ae60;
        }

        .marge-negative {
            color: #e74c3c;
        }
    </style>

    <script>
        // Calcul automatique de la marge
        function calculerMarge() {
            const prixAchat = parseFloat(document.getElementById('prix_achat').value) || 0;
            const prixVente = parseFloat(document.getElementById('prix_vente').value) || 0;
            
            const margeDiv = document.getElementById('marge-preview');
            if (!margeDiv) {
                // Créer l'élément si il n'existe pas
                const div = document.createElement('div');
                div.id = 'marge-preview';
                div.className = 'marge-preview';
                document.getElementById('prix_vente').parentNode.appendChild(div);
            }
            
            if (prixAchat > 0 && prixVente > 0) {
                const marge = prixVente - prixAchat;
                const margePct = ((marge / prixAchat) * 100);
                
                document.getElementById('marge-preview').innerHTML = `
                    <strong>Marge : ${marge.toFixed(2)} € 
                    (<span class="${margePct >= 0 ? 'marge-positive' : 'marge-negative'}">${margePct >= 0 ? '+' : ''}${margePct.toFixed(1)}%</span>)</strong>
                `;
            } else if (prixVente > 0) {
                document.getElementById('marge-preview').innerHTML = `
                    <strong>Prix de vente : ${prixVente.toFixed(2)} €</strong>
                `;
            } else {
                document.getElementById('marge-preview').innerHTML = '';
            }
        }

        // Génération automatique de référence
        function genererReference() {
            const nom = document.getElementById('nom').value;
            const marque = document.getElementById('marque').value;
            const reference = document.getElementById('reference');
            
            if (nom && !reference.value.includes('PROD-')) {
                let ref = '';
                if (marque) {
                    ref += marque.substring(0, 3).toUpperCase() + '-';
                }
                ref += nom.substring(0, 3).toUpperCase() + '-';
                ref += new Date().getFullYear() + '-';
                ref += Math.random().toString(36).substring(2, 8).toUpperCase();
                
                reference.value = ref;
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('prix_achat').addEventListener('input', calculerMarge);
            document.getElementById('prix_vente').addEventListener('input', calculerMarge);
            document.getElementById('nom').addEventListener('blur', genererReference);
            document.getElementById('marque').addEventListener('blur', genererReference);
            
            // Calcul initial
            calculerMarge();
        });

        // Validation du formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const reference = document.getElementById('reference').value.trim();
            const prixVente = parseFloat(document.getElementById('prix_vente').value);
            
            if (!nom) {
                alert('Le nom du lot est obligatoire');
                e.preventDefault();
                return;
            }
            
            if (!reference) {
                alert('La référence est obligatoire');
                e.preventDefault();
                return;
            }
            
            if (!prixVente || prixVente <= 0) {
                alert('Le prix de vente doit être supérieur à 0');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>