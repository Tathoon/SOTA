<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php

// require_once '../../includes/session.php';
// require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $nom = sanitizeInput($_POST['nom']);
        $description = sanitizeInput($_POST['description']);

        if (empty($nom)) {
            throw new Exception("Le nom de la catégorie est obligatoire");
        }

        $result = $manager->ajouterCategorie($nom, $description);
        
        if ($result) {
            logActivite('creation_categorie', [
                'nom' => $nom,
                'description' => $description
            ], $user['id']);
            
            header('Location: categories.php?message=' . urlencode('Catégorie créée avec succès'));
            exit();
        } else {
            throw new Exception("Erreur lors de la création de la catégorie");
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
    <title>Nouvelle catégorie - SOTA Fashion</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container dashboard-container">
        <?php include '../../includes/sidebar.php'; ?>

        <form method="POST" class="category-form">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-info-circle"></i> Informations de la catégorie
        </h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="nom">Nom de la catégorie *</label>
                <input type="text" id="nom" name="nom" class="form-control" 
                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" 
                       placeholder="Ex: Robes, Hauts, Chaussures..." 
                       required autofocus>
                <small class="form-help">Nom qui apparaîtra dans le catalogue</small>
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="4"
                      placeholder="Description détaillée de la catégorie et du type de produits qu'elle contient..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            <small class="form-help">Description optionnelle pour aider à identifier le contenu de cette catégorie</small>
        </div>
    </div>

    <!-- Preview des catégories existantes -->
    <div class="form-section">
        <h3 class="section-title">
            <i class="fas fa-list"></i> Catégories existantes
        </h3>
        <div class="existing-categories">
            <?php
            $categories_existantes = $manager->getCategories();
            if (empty($categories_existantes)):
            ?>
                <p style="color: #666; font-style: italic;">Aucune catégorie existante. Cette sera la première !</p>
            <?php else: ?>
                <div class="categories-preview">
                    <?php foreach ($categories_existantes as $cat): ?>
                        <div class="category-tag">
                            <i class="fas fa-tag"></i>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="form-help">Assurez-vous que votre nouvelle catégorie ne fait pas doublon</small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions -->
    <div class="form-actions">
        <a href="categories.php" class="btn-border">
            <i class="fas fa-times"></i> Annuler
        </a>
        <button type="submit" class="btn-orange">
            <i class="fas fa-save"></i> Créer la catégorie
        </button>
    </div>
</form>

        
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
                <h1><i class="fas fa-plus"></i> Nouvelle catégorie</h1>
                <p class="dashboard-subtitle">Créer une nouvelle catégorie pour organiser vos produits</p>
            </section>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
                                    <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="categories.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Créer la catégorie
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .category-form {
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        .form-actions {
            padding: 25px 30px;
            background: #f8f9fa;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            border-top: 1px solid #eee;
        }

        .existing-categories {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .categories-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .category-tag {
            background: white;
            color: var(--primary-color);
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid var(--primary-color);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .category-tag i {
            font-size: 11px;
        }
    </style>

    <script>
        // Validation en temps réel
        document.getElementById('nom').addEventListener('input', function() {
            const nom = this.value.trim();
            const existingCategories = [
                <?php 
                $categories_existantes = $manager->getCategories();
                foreach ($categories_existantes as $cat) {
                    echo "'" . addslashes($cat['nom']) . "',";
                }
                ?>
            ];
            
            // Vérification des doublons
            const isDuplicate = existingCategories.some(cat => 
                cat.toLowerCase() === nom.toLowerCase()
            );
            
            if (isDuplicate && nom.length > 0) {
                this.style.borderColor = '#e74c3c';
                
                // Afficher un message d'erreur
                let errorMsg = document.getElementById('duplicate-error');
                if (!errorMsg) {
                    errorMsg = document.createElement('small');
                    errorMsg.id = 'duplicate-error';
                    errorMsg.style.color = '#e74c3c';
                    errorMsg.style.marginTop = '5px';
                    this.parentNode.appendChild(errorMsg);
                }
                errorMsg.textContent = 'Cette catégorie existe déjà';
            } else {
                this.style.borderColor = nom.length > 0 ? '#27ae60' : '#e1e5e9';
                
                // Supprimer le message d'erreur
                const errorMsg = document.getElementById('duplicate-error');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
        });

        // Validation du formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            
            if (!nom) {
                alert('Le nom de la catégorie est obligatoire');
                e.preventDefault();
                return;
            }
            
            if (nom.length < 2) {
                alert('Le nom de la catégorie doit contenir au moins 2 caractères');
                e.preventDefault();
                return;
            }
            
            // Vérification finale des doublons
            const existingCategories = [
                <?php 
                foreach ($categories_existantes as $cat) {
                    echo "'" . addslashes($cat['nom']) . "',";
                }
                ?>
            ];
            
            const isDuplicate = existingCategories.some(cat => 
                cat.toLowerCase() === nom.toLowerCase()
            );
            
            if (isDuplicate) {
                alert('Cette catégorie existe déjà. Veuillez choisir un autre nom.');
                e.preventDefault();
                return;
            }
        });

        // Suggestions de catégories
        const suggestions = [
            'Robes', 'Hauts', 'Bas', 'Vestes & Manteaux', 'Lingerie', 
            'Accessoires', 'Chaussures', 'Maillots de bain', 'Sport & Détente',
            'Mariage & Cérémonie', 'Jeans', 'Pulls & Gilets', 'Chemisiers',
            'Jupes', 'Pantalons', 'Shorts', 'Combinaisons', 'Blazers'
        ];

        // Ajouter l'autocomplétion
        document.getElementById('nom').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const matchingSuggestions = suggestions.filter(s => 
                s.toLowerCase().includes(input) && input.length >= 2
            );
            
            // Ici on pourrait ajouter une liste déroulante de suggestions
            // Pour simplifier, on se contente de la validation
        });
    </script>
</body>
</html>

            