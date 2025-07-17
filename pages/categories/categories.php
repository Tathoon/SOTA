<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des catégories avec comptage des produits
try {
    $stmt = $manager->db->prepare("
        SELECT c.*, COUNT(p.id) as nb_produits
        FROM categories c
        LEFT JOIN produits p ON c.id = p.categorie_id AND p.actif = 1
        WHERE c.actif = 1
        GROUP BY c.id
        ORDER BY c.nom
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $error = "Erreur lors du chargement des catégories";
}

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des catégories - SOTA Fashion</title>
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
                <h1><i class="fas fa-tags"></i> Gestion des catégories</h1>
                <p class="dashboard-subtitle">Organisation du catalogue prêt-à-porter - <?= count($categories) ?> catégories</p>
            </section>

            <?php if ($message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Actions rapides -->
            <section class="dashboard-section">
                <div class="stock-actions">
                    <a href="nouvelle.php" class="btn-orange">
                        <i class="fas fa-plus"></i> Nouvelle catégorie
                    </a>
                    <a href="export.php?format=csv" class="btn-border">
                        <i class="fas fa-download"></i> Exporter CSV
                    </a>
                </div>
            </section>

            <!-- Liste des catégories -->
            <div class="categories-container">
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <h3>Aucune catégorie créée</h3>
                        <p>Organisez votre catalogue en créant des catégories pour vos produits</p>
                        <a href="nouvelle.php" class="btn-orange">
                            <i class="fas fa-plus"></i> Créer la première catégorie
                        </a>
                    </div>
                <?php else: ?>
                    <div class="categories-grid">
                        <?php foreach ($categories as $categorie): ?>
                            <div class="category-card">
                                <div class="category-header">
                                    <div class="category-icon">
                                        <i class="fas fa-tag"></i>
                                    </div>
                                    <div class="category-info">
                                        <h3><?= htmlspecialchars($categorie['nom']) ?></h3>
                                        <p><?= htmlspecialchars($categorie['description']) ?: 'Aucune description' ?></p>
                                    </div>
                                </div>
                                
                                <div class="category-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?= $categorie['nb_produits'] ?></span>
                                        <span class="stat-label">produits</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?= formatDate($categorie['created_at']) ?></span>
                                        <span class="stat-label">créée le</span>
                                    </div>
                                </div>
                                
                                <div class="category-actions">
                                    <a href="../produits/produits.php?category=<?= $categorie['id'] ?>" class="btn-border btn-small">
                                        <i class="fas fa-eye"></i> Voir produits
                                    </a>
                                    <a href="modifier.php?id=<?= $categorie['id'] ?>" class="btn-border btn-small">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <button onclick="confirmerSuppressionCategorie(<?= $categorie['id'] ?>, <?= $categorie['nb_produits'] ?>)" class="btn-danger btn-small">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        .categories-container {
            margin: 0 30px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .category-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .category-header {
            padding: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .category-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), #e55a2b);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .category-info {
            flex: 1;
        }

        .category-info h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .category-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .category-stats {
            padding: 0 25px;
            display: flex;
            gap: 30px;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            background: #f8f9fa;
        }

        .stat-item {
            padding: 15px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .category-actions {
            padding: 20px 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .category-actions .btn-small {
            flex: 1;
            min-width: 0;
            text-align: center;
            padding: 8px 12px;
            font-size: 12px;
        }
    </style>

    <script>
    function supprimerCategorie(id, nbProduits = 0) {
        let message = "Êtes-vous sûr de vouloir supprimer cette catégorie ?\n\nCette action est irréversible.";

        if (nbProduits > 0) {
            message += `\n⚠️ Attention : cela supprimera également ${nbProduits} produit(s) lié(s).`;
        }

        if (confirm(message)) {
            // Créer un formulaire pour envoyer la requête POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'supprimer.php';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?= generateCSRFToken() ?>';
            
            form.appendChild(idInput);
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>


</body>
</html>