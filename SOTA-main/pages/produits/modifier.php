<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$error = '';
$produit_id = (int)($_GET['id'] ?? 0);

// Récupération du produit
$produit = $manager->getProduitById($produit_id);
if (!$produit) {
    header('Location: produits.php?error=' . urlencode('Lot non trouvé'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $data = [
            'reference' => sanitizeInput($_POST['reference']),
            'nom' => sanitizeInput($_POST['nom']),
            'description' => sanitizeInput($_POST['description']),
            'categorie_id' => !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null,
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
            'collection' => sanitizeInput($_POST['collection'])
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

        $result = $manager->modifierProduit($produit_id, $data);
        
        if ($result) {
            logActivite('modification_produit', [
                'produit_id' => $produit_id,
                'reference' => $data['reference'],
                'nom' => $data['nom']
            ], $user['id']);
            
            header('Location: produits.php?message=' . urlencode('Lot modifié avec succès'));
            exit();
        } else {
            throw new Exception("Erreur lors de la modification du lot");
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
    <title>Modifier le lot - SOTA Fashion</title>
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
                <h1><i class="fas fa-edit"></i> Modifier le lot</h1>
                <p class="dashboard-subtitle">Modification de <?= htmlspecialchars($produit['nom']) ?></p>
            </section>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Informations actuelles -->
            <div class="current-product-info">
                <div class="product-summary">
                    <div class="product-main">
                        <h3><?= htmlspecialchars($produit['nom']) ?></h3>
                        <p><?= htmlspecialchars($produit['reference']) ?></p>
                        <?= getStatusBadge($produit['statut_stock']) ?>
                    </div>
                    <div class="product-stats">
                        <div class="stat-item">
                            <label>Stock actuel</label>
                            <span class="stock-value <?= $produit['statut_stock'] ?>"><?= $produit['stock_actuel'] ?></span>
                        </div>
                        <div class="stat-item">
                            <label>Prix actuel</label>
                            <span><?= formatPrice($produit['prix_vente']) ?></span>
                        </div>
                        <div class="stat-item">
                            <label>Dernière MAJ</label>
                            <span><?= formatDateTime($produit['updated_at']) ?></span>
                        </div>
                    </div>
                    <div class="product-actions">
                        <a href="details.php?id=<?= $produit['id'] ?>" class="btn-border">
                            <i class="fas fa-eye"></i> Voir détails
                        </a>
                        <a href="../stocks/mouvement.php?produit=<?= $produit['id'] ?>" class="btn-border">
                            <i class="fas fa-exchange-alt"></i> Gérer stock
                        </a>
                    </div>
                </div>
            </div>

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
                                   value="<?= htmlspecialchars($_POST['reference'] ?? $produit['reference']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nom">Nom du lot *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['nom'] ?? $produit['nom']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="marque">Marque</label>
                            <input type="text" id="marque" name="marque" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['marque'] ?? $produit['marque']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="collection">Collection</label>
                            <input type="text" id="collection" name="collection" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['collection'] ?? $produit['collection']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $produit['description']) ?></textarea>
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
                                    <option value="<?= $cat['id'] ?>" 
                                            <?= (($_POST['categorie_id'] ?? $produit['categorie_id']) == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="taille">Taille</label>
                            <select id="taille" name="taille" class="form-control">
                                <option value="">Sélectionner une taille</option>
                                <?php 
                                $tailles = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '34', '36', '38', '40', '42', '44', '46', 'Unique'];
                                foreach ($tailles as $taille): 
                                ?>
                                    <option value="<?= $taille ?>" 
                                            <?= (($_POST['taille'] ?? $produit['taille']) === $taille) ? 'selected' : '' ?>>
                                        <?= $taille === 'Unique' ? 'Taille unique' : $taille ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="couleur">Couleur</label>
                            <input type="text" id="couleur" name="couleur" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['couleur'] ?? $produit['couleur']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="saison">Saison</label>
                            <select id="saison" name="saison" class="form-control">
                                <option value="">Sélectionner une saison</option>
                                <?php 
                                $saisons = ['Printemps/Été', 'Automne/Hiver', 'Toute saison'];
                                foreach ($saisons as $saison): 
                                ?>
                                    <option value="<?= $saison ?>" 
                                            <?= (($_POST['saison'] ?? $produit['saison']) === $saison) ? 'selected' : '' ?>>
                                        <?= $saison ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Prix et paramètres -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-euro-sign"></i> Prix et paramètres
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prix_achat">Prix d'achat (€)</label>
                            <input type="number" id="prix_achat" name="prix_achat" class="form-control" 
                                   step="0.01" min="0" 
                                   value="<?= $_POST['prix_achat'] ?? $produit['prix_achat'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="prix_vente">Prix de vente (€) *</label>
                            <input type="number" id="prix_vente" name="prix_vente" class="form-control" 
                                   step="0.01" min="0.01" 
                                   value="<?= $_POST['prix_vente'] ?? $produit['prix_vente'] ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="seuil_minimum">Seuil d'alerte</label>
                            <input type="number" id="seuil_minimum" name="seuil_minimum" class="form-control" 
                                   min="0" value="<?= $_POST['seuil_minimum'] ?? $produit['seuil_minimum'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="lot_minimum">Lot minimum</label>
                            <input type="number" id="lot_minimum" name="lot_minimum" class="form-control" 
                                   min="1" value="<?= $_POST['lot_minimum'] ?? $produit['lot_minimum'] ?>">
                        </div>
                    </div>
                    
                    <!-- Aperçu de la marge -->
                    <div id="marge-preview" class="marge-preview"></div>
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
                                      placeholder="Ex: 100% Coton, 95% Viscose 5% Elasthanne..."><?= htmlspecialchars($_POST['composition'] ?? $produit['composition']) ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="poids">Poids (g)</label>
                            <input type="number" id="poids" name="poids" class="form-control" 
                                   step="0.1" min="0" value="<?= $_POST['poids'] ?? $produit['poids'] ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emplacement">Emplacement</label>
                            <input type="text" id="emplacement" name="emplacement" class="form-control" 
                                   placeholder="Ex: A1-B2, Rayon 3-Étagère 2..." 
                                   value="<?= htmlspecialchars($_POST['emplacement'] ?? $produit['emplacement']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Note sur le stock -->
                <div class="form-section">
                    <div class="stock-warning">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Note importante :</strong>
                            <p>Le stock actuel (<?= $produit['stock_actuel'] ?> unités) ne peut pas être modifié ici. 
                               Utilisez la <a href="../stocks/mouvement.php?produit=<?= $produit['id'] ?>">gestion des mouvements de stock</a> 
                               pour ajuster les quantités.</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="produits.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <a href="details.php?id=<?= $produit['id'] ?>" class="btn-border">
                        <i class="fas fa-eye"></i> Voir détails
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .current-product-info {
            margin: 0 30px 20px;
        }

        .product-summary {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary-color);
        }

        .product-main h3 {
            margin: 0 0 5px 0;
            color: var(--secondary-color);
            font-size: 20px;
        }

        .product-main p {
            margin: 0 0 10px 0;
            color: #666;
            font-family: monospace;
        }

        .product-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .stat-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .stat-item span {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .stock-value.alerte {
            color: var(--warning-color);
        }

        .stock-value.rupture {
            color: var(--danger-color);
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

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

        .marge-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
            border-left: 4px solid var(--info-color);
        }

        .marge-positive {
            color: #27ae60;
            font-weight: 600;
        }

        .marge-negative {
            color: #e74c3c;
            font-weight: 600;
        }

        .stock-warning {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: #e8f4fd;
            border: 1px solid #b8daff;
            border-radius: 8px;
            color: #004085;
        }

        .stock-warning i {
            color: #007bff;
            font-size: 20px;
            margin-top: 2px;
        }

        .stock-warning strong {
            display: block;
            margin-bottom: 5px;
        }

        .stock-warning p {
            margin: 0;
            line-height: 1.5;
        }

        .stock-warning a {
            color: #007bff;
            text-decoration: underline;
        }

        .form-actions {
            padding: 25px 30px;
            background: #f8f9fa;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #eee;
        }

        @media (max-width: 768px) {
            .product-summary {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }

            .product-stats {
                gap: 20px;
            }

            .product-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <script>
        // Calcul automatique de la marge
        function calculerMarge() {
            const prixAchat = parseFloat(document.getElementById('prix_achat').value) || 0;
            const prixVente = parseFloat(document.getElementById('prix_vente').value) || 0;
            const margeDiv = document.getElementById('marge-preview');
            
            if (prixAchat > 0 && prixVente > 0) {
                const marge = prixVente - prixAchat;
                const margePct = ((marge / prixAchat) * 100);
                
                margeDiv.innerHTML = `
                    <strong>Marge : ${marge.toFixed(2)} € 
                    (<span class="${margePct >= 0 ? 'marge-positive' : 'marge-negative'}">${margePct >= 0 ? '+' : ''}${margePct.toFixed(1)}%</span>)</strong>
                    <br><small>Marge précédente : ${(<?= $produit['prix_vente'] ?> - <?= $produit['prix_achat'] ?: 0 ?>).toFixed(2)} €</small>
                `;
            } else if (prixVente > 0) {
                margeDiv.innerHTML = `
                    <strong>Prix de vente : ${prixVente.toFixed(2)} €</strong>
                `;
            } else {
                margeDiv.innerHTML = '';
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('prix_achat').addEventListener('input', calculerMarge);
            document.getElementById('prix_vente').addEventListener('input', calculerMarge);
            
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

            // Confirmation des changements importants
            const originalPrix = <?= $produit['prix_vente'] ?>;
            const newPrix = prixVente;
            
            if (Math.abs(newPrix - originalPrix) / originalPrix > 0.2) { // +/- 20%
                if (!confirm(`Attention : Le prix de vente a changé de ${((newPrix - originalPrix) / originalPrix * 100).toFixed(1)}%.\n\nConfirmer cette modification importante ?`)) {
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</body>
</html>