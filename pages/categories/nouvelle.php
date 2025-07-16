<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom' => trim($_POST['nom']),
            'description' => trim($_POST['description'])
        ];

        // Validation
        if (empty($data['nom'])) {
            throw new Exception("Le nom de la catégorie est obligatoire");
        }

        if (strlen($data['nom']) > 100) {
            throw new Exception("Le nom de la catégorie ne peut pas dépasser 100 caractères");
        }

        // Vérifier si la catégorie existe déjà
        $stmt = $manager->db->prepare("SELECT COUNT(*) FROM categories WHERE nom = ? AND actif = 1");
        $stmt->execute([$data['nom']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cette catégorie existe déjà");
        }

        // Ajout de la catégorie
        $stmt = $manager->db->prepare("
            INSERT INTO categories (nom, description, actif) 
            VALUES (?, ?, 1)
        ");
        $stmt->execute([$data['nom'], $data['description']]);

        $message = "Catégorie '{$data['nom']}' créée avec succès";
        
        // Redirection avec message
        header("Location: categories.php?message=" . urlencode($message));
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des catégories existantes pour preview
$stmt = $manager->db->prepare("SELECT nom, description FROM categories WHERE actif = 1 ORDER BY nom");
$stmt->execute();
$categories_existantes = $stmt->fetchAll();
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
                <p class="dashboard-subtitle">Créer une nouvelle catégorie de produits</p>
            </section>

            <?php if ($message): ?>
                <div class="dashboard-section" style="background: #d4edda; border-left: 4px solid #28a745; margin: 20px 30px;">
                    <p style="color: #155724; margin: 0;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="dashboard-section" style="background: #f8d7da; border-left: 4px solid #dc3545; margin: 20px 30px;">
                    <p style="color: #721c24; margin: 0;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="product-form">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Informations de base
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom de la catégorie *</label>
                            <input type="text" 
                                   id="nom" 
                                   name="nom" 
                                   class="form-control"
                                   required 
                                   maxlength="100"
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                                   placeholder="Ex: Robes, Hauts, Accessoires...">
                            <small>Nom unique pour identifier cette catégorie</small>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" 
                                      name="description" 
                                      class="form-control"
                                      rows="3" 
                                      placeholder="Description optionnelle de la catégorie..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <small>Description optionnelle pour aider à identifier le contenu de cette catégorie</small>
                        </div>
                    </div>
                </div>

                <!-- Preview des catégories existantes -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-list"></i> Catégories existantes
                    </h3>
                    
                    <?php if (empty($categories_existantes)): ?>
                        <p style="color: #666; font-style: italic;">Aucune catégorie existante. Cette sera la première !</p>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                            <?php foreach ($categories_existantes as $cat): ?>
                                <span class="status actif" style="display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: #666;">Assurez-vous que votre nouvelle catégorie ne fait pas doublon</small>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="categories.php" class="btn-border">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-plus"></i> Créer la catégorie
                    </button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>