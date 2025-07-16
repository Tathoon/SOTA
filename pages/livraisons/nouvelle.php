<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Livreur']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'commande_id' => (int)$_POST['commande_id'],
            'date_prevue' => $_POST['date_prevue'],
            'transporteur' => trim($_POST['transporteur']),
            'numero_suivi' => trim($_POST['numero_suivi']),
            'notes' => trim($_POST['notes'])
        ];

        // Validation
        if (empty($data['commande_id'])) {
            throw new Exception("Vous devez sélectionner une commande");
        }

        if (empty($data['date_prevue'])) {
            throw new Exception("La date de livraison prévue est obligatoire");
        }

        if (empty($data['transporteur'])) {
            throw new Exception("Le transporteur est obligatoire");
        }

        // Vérifier que la commande existe et n'a pas déjà de livraison
        $stmt = $manager->db->prepare("
            SELECT c.*, COUNT(l.id) as nb_livraisons
            FROM commandes c
            LEFT JOIN livraisons l ON c.id = l.commande_id
            WHERE c.id = ? AND c.statut IN ('confirmee', 'en_preparation', 'expediee')
            GROUP BY c.id
        ");
        $stmt->execute([$data['commande_id']]);
        $commande = $stmt->fetch();

        if (!$commande) {
            throw new Exception("Commande non trouvée ou statut incorrect");
        }

        if ($commande['nb_livraisons'] > 0) {
            throw new Exception("Cette commande a déjà une livraison planifiée");
        }

        // Créer la livraison
        $stmt = $manager->db->prepare("
            INSERT INTO livraisons (commande_id, date_prevue, transporteur, numero_suivi, statut, notes)
            VALUES (?, ?, ?, ?, 'planifiee', ?)
        ");
        $stmt->execute([
            $data['commande_id'],
            $data['date_prevue'],
            $data['transporteur'],
            $data['numero_suivi'] ?: null,
            $data['notes']
        ]);

        // Mettre à jour le statut de la commande si nécessaire
        if ($commande['statut'] === 'confirmee') {
            $stmt = $manager->db->prepare("UPDATE commandes SET statut = 'en_preparation' WHERE id = ?");
            $stmt->execute([$data['commande_id']]);
        }

        $message = "Livraison planifiée avec succès";
        
        // Redirection
        header("Location: livraisons.php?message=" . urlencode($message));
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des commandes disponibles pour livraison
$stmt = $manager->db->prepare("
    SELECT c.*, 
           COUNT(l.id) as nb_livraisons
    FROM commandes c
    LEFT JOIN livraisons l ON c.id = l.commande_id
    WHERE c.statut IN ('confirmee', 'en_preparation', 'expediee')
    GROUP BY c.id
    HAVING nb_livraisons = 0
    ORDER BY c.date_commande DESC
");
$stmt->execute();
$commandes_disponibles = $stmt->fetchAll();

// Liste des transporteurs courants
$transporteurs = ['Chronopost', 'Colissimo', 'DHL', 'UPS', 'TNT', 'GLS', 'Autre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle livraison - SOTA Fashion</title>
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
                <h1><i class="fas fa-truck"></i> Nouvelle livraison</h1>
                <p class="dashboard-subtitle">Planifier une livraison pour une commande</p>
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

            <?php if (empty($commandes_disponibles)): ?>
                <div class="dashboard-section" style="text-align: center; padding: 50px;">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">Aucune commande disponible</h3>
                    <p style="color: #999; margin-bottom: 20px;">Toutes les commandes ont déjà une livraison planifiée ou ne sont pas prêtes pour la livraison.</p>
                    <a href="livraisons.php" class="btn-orange">
                        <i class="fas fa-arrow-left"></i> Retour aux livraisons
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" class="order-form">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-shopping-cart"></i> Sélection de la commande
                        </h3>
                        
                        <div class="form-group">
                            <label for="commande_id">Commande à livrer *</label>
                            <select id="commande_id" name="commande_id" class="form-control" required>
                                <option value="">Sélectionnez une commande</option>
                                <?php foreach ($commandes_disponibles as $commande): ?>
                                    <option value="<?= $commande['id'] ?>" 
                                            <?= ($_POST['commande_id'] ?? '') == $commande['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($commande['numero_commande']) ?> - 
                                        <?= htmlspecialchars($commande['client_nom']) ?> - 
                                        <?= number_format($commande['total'], 2) ?>€
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Seules les commandes prêtes à être livrées sont affichées</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i> Détails de la livraison
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="date_prevue">Date de livraison prévue *</label>
                                <input type="date" 
                                       id="date_prevue" 
                                       name="date_prevue" 
                                       class="form-control"
                                       required 
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= htmlspecialchars($_POST['date_prevue'] ?? date('Y-m-d', strtotime('+2 days'))) ?>">
                                <small>Date à laquelle la livraison est prévue</small>
                            </div>

                            <div class="form-group">
                                <label for="transporteur">Transporteur *</label>
                                <select id="transporteur" name="transporteur" class="form-control" required>
                                    <option value="">Sélectionnez un transporteur</option>
                                    <?php foreach ($transporteurs as $transporteur): ?>
                                        <option value="<?= $transporteur ?>" 
                                                <?= ($_POST['transporteur'] ?? '') === $transporteur ? 'selected' : '' ?>>
                                            <?= $transporteur ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Société de transport qui effectuera la livraison</small>
                            </div>

                            <div class="form-group">
                                <label for="numero_suivi">Numéro de suivi</label>
                                <input type="text" 
                                       id="numero_suivi" 
                                       name="numero_suivi" 
                                       class="form-control"
                                       placeholder="Ex: 1234567890"
                                       value="<?= htmlspecialchars($_POST['numero_suivi'] ?? '') ?>">
                                <small>Numéro de suivi du colis (optionnel, peut être ajouté plus tard)</small>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" 
                                          name="notes" 
                                          class="form-control"
                                          rows="3" 
                                          placeholder="Instructions spéciales, remarques..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                                <small>Remarques ou instructions particulières pour cette livraison</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="livraisons.php" class="btn-border">
                            <i class="fas fa-arrow-left"></i> Annuler
                        </a>
                        <button type="submit" class="btn-orange">
                            <i class="fas fa-truck"></i> Planifier la livraison
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-remplir les informations de la commande sélectionnée
        document.getElementById('commande_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                console.log('Commande sélectionnée:', selectedOption.text);
            }
        });
    </script>
</body>
</html>