<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Restriction d'accès : Admin ou Gérant uniquement
requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$categories = $manager->getCategories();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement de l'ajout de catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!empty($nom)) {
        if ($manager->ajouterCategorie($nom, $description)) {
            $message = "Catégorie ajoutée avec succès";
            $categories = $manager->getCategories(); // Recharger
        } else {
            $error = "Erreur lors de l'ajout de la catégorie";
        }
    } else {
        $error = "Le nom de la catégorie est obligatoire";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des catégories - SOTA</title>
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
                <h2><i class="fas fa-tags"></i> Gestion des catégories</h2>
                <p class="dashboard-subtitle">Organisez les produits par catégorie pour faciliter la gestion</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulaire d'ajout rapide -->
            <div style="background: white; margin: 30px; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #ff6b35; margin-bottom: 15px;">Ajouter une catégorie</h3>
                <form method="POST" style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 15px; align-items: end;">
                    <input type="hidden" name="action" value="ajouter">
                    <div>
                        <label>Nom *</label>
                        <input type="text" name="nom" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label>Description</label>
                        <input type="text" name="description" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <button type="submit" class="btn-orange">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>

            <table class="stock-table">
                <thead>
                    <tr>
                        <th>Nom de la catégorie</th>
                        <th>Description</th>
                        <th>Nb produits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $categorie): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($categorie['nom'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($categorie['description'] ?? '-') ?></td>
                        <td>
                            <?php
                            // Compter les produits dans cette catégorie
                            $produits = $manager->getProduits('', $categorie['id']);
                            echo count($produits);
                            ?>
                        </td>
                        <td class="actions">
                            <a href="../produits/produits.php?category=<?= $categorie['id'] ?>" class="btn-border btn-small">
                                <i class="fas fa-eye"></i> Voir produits
                            </a>
                            <a href="modifier.php?id=<?= $categorie['id'] ?>" class="btn-border btn-small">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="stock-actions">
                <a href="../produits/nouveau.php" class="btn-border">
                    <i class="fas fa-box"></i> Ajouter un produit
                </a>
                <button onclick="window.location.href='export.php'" class="btn-border">
                    <i class="fas fa-download"></i> Exporter
                </button>
            </div>
        </main>
    </div>
</body>
</html>