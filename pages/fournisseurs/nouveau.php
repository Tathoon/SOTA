<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

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

        $data = [
            'nom' => sanitizeInput($_POST['nom']),
            'siret' => sanitizeInput($_POST['siret']),
            'contact' => sanitizeInput($_POST['contact']),
            'telephone' => sanitizeInput($_POST['telephone']),
            'email' => sanitizeInput($_POST['email']),
            'adresse' => sanitizeInput($_POST['adresse']),
            'ville' => sanitizeInput($_POST['ville']),
            'code_postal' => sanitizeInput($_POST['code_postal']),
            'pays' => sanitizeInput($_POST['pays']) ?: 'France',
            'delais_livraison' => (int)($_POST['delais_livraison'] ?? 7),
            'conditions_paiement' => sanitizeInput($_POST['conditions_paiement']),
            'specialite_mode' => sanitizeInput($_POST['specialite_mode'])
        ];

        // Validation
        if (empty($data['nom'])) {
            throw new Exception("Le nom du fournisseur est obligatoire");
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'email n'est pas valide");
        }

        if (!empty($data['siret']) && (strlen($data['siret']) !== 14 || !ctype_digit($data['siret']))) {
            throw new Exception("Le SIRET doit contenir exactement 14 chiffres");
        }

        $result = $manager->ajouterFournisseur($data);
        
        if ($result) {
            logActivite('creation_fournisseur', [
                'nom' => $data['nom'],
                'ville' => $data['ville']
            ], $user['id']);
            
            header('Location: fournisseurs.php?message=' . urlencode('Fournisseur créé avec succès'));
            exit();
        } else {
            throw new Exception("Erreur lors de la création du fournisseur");
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
    <title>Nouveau fournisseur - SOTA Fashion</title>
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
                <h1><i class="fas fa-plus"></i> Nouveau fournisseur</h1>
                <p class="dashboard-subtitle">Ajouter un nouveau partenaire prêt-à-porter</p>
            </section>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="supplier-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <!-- Informations générales -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i> Informations générales
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom du fournisseur *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="siret">SIRET</label>
                            <input type="text" id="siret" name="siret" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['siret'] ?? '') ?>" 
                                   pattern="[0-9]{14}" maxlength="14"
                                   placeholder="14 chiffres">
                            <small class="form-help">Format : 12345678901234</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialite_mode">Spécialité mode</label>
                            <select id="specialite_mode" name="specialite_mode" class="form-control">
                                <option value="">Sélectionner...</option>
                                <option value="Prêt-à-porter haut de gamme" <?= ($_POST['specialite_mode'] ?? '') === 'Prêt-à-porter haut de gamme' ? 'selected' : '' ?>>Prêt-à-porter haut de gamme</option>
                                <option value="Prêt-à-porter casual" <?= ($_POST['specialite_mode'] ?? '') === 'Prêt-à-porter casual' ? 'selected' : '' ?>>Prêt-à-porter casual</option>
                                <option value="Lingerie fine" <?= ($_POST['specialite_mode'] ?? '') === 'Lingerie fine' ? 'selected' : '' ?>>Lingerie fine</option>
                                <option value="Chaussures femmes" <?= ($_POST['specialite_mode'] ?? '') === 'Chaussures femmes' ? 'selected' : '' ?>>Chaussures femmes</option>
                                <option value="Maroquinerie et accessoires" <?= ($_POST['specialite_mode'] ?? '') === 'Maroquinerie et accessoires' ? 'selected' : '' ?>>Maroquinerie et accessoires</option>
                                <option value="Textiles et matières premières" <?= ($_POST['specialite_mode'] ?? '') === 'Textiles et matières premières' ? 'selected' : '' ?>>Textiles et matières premières</option>
                                <option value="Confection sur mesure" <?= ($_POST['specialite_mode'] ?? '') === 'Confection sur mesure' ? 'selected' : '' ?>>Confection sur mesure</option>
                                <option value="Import international" <?= ($_POST['specialite_mode'] ?? '') === 'Import international' ? 'selected' : '' ?>>Import international</option>
                                <option value="Créations originales" <?= ($_POST['specialite_mode'] ?? '') === 'Créations originales' ? 'selected' : '' ?>>Créations originales</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Contact
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="contact">Nom du contact</label>
                            <input type="text" id="contact" name="contact" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Adresse
                    </h3>
                    <div class="form-group">
                        <label for="adresse">Adresse complète</label>
                        <textarea id="adresse" name="adresse" class="form-control" rows="2"><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="code_postal">Code postal</label>
                            <input type="text" id="code_postal" name="code_postal" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['code_postal'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="ville">Ville</label>
                            <input type="text" id="ville" name="ville" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['ville'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="pays">Pays</label>
                            <select id="pays" name="pays" class="form-control">
                                <option value="France" <?= ($_POST['pays'] ?? 'France') === 'France' ? 'selected' : '' ?>>France</option>
                                <option value="Belgique" <?= ($_POST['pays'] ?? '') === 'Belgique' ? 'selected' : '' ?>>Belgique</option>
                                <option value="Suisse" <?= ($_POST['pays'] ?? '') === 'Suisse' ? 'selected' : '' ?>>Suisse</option>
                                <option value="Italie" <?= ($_POST['pays'] ?? '') === 'Italie' ? 'selected' : '' ?>>Italie</option>
                                <option value="Espagne" <?= ($_POST['pays'] ?? '') === 'Espagne' ? 'selected' : '' ?>>Espagne</option>
                                <option value="Allemagne" <?= ($_POST['pays'] ?? '') === 'Allemagne' ? 'selected' : '' ?>>Allemagne</option>
                                <option value="Portugal" <?= ($_POST['pays'] ?? '') === 'Portugal' ? 'selected' : '' ?>>Portugal</option>
                                <option value="Chine" <?= ($_POST['pays'] ?? '') === 'Chine' ? 'selected' : '' ?>>Chine</option>
                                <option value="Turquie" <?= ($_POST['pays'] ?? '') === 'Turquie' ? 'selected' : '' ?>>Turquie</option>
                                <option value="Maroc" <?= ($_POST['pays'] ?? '') === 'Maroc' ? 'selected' : '' ?>>Maroc</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Conditions commerciales -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-handshake"></i> Conditions commerciales
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="delais_livraison">Délai de livraison (jours)</label>
                            <input type="number" id="delais_livraison" name="delais_livraison" class="form-control" 
                                   value="<?= $_POST['delais_livraison'] ?? '7' ?>" min="1" max="365">
                            <small class="form-help">Délai moyen de livraison en jours ouvrés</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="conditions_paiement">Conditions de paiement</label>
                            <select id="conditions_paiement" name="conditions_paiement" class="form-control">
                                <option value="">Sélectionner...</option>
                                <option value="Comptant" <?= ($_POST['conditions_paiement'] ?? '') === 'Comptant' ? 'selected' : '' ?>>Comptant</option>
                                <option value="15 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '15 jours net' ? 'selected' : '' ?>>15 jours net</option>
                                <option value="30 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '30 jours net' ? 'selected' : '' ?>>30 jours net</option>
                                <option value="30 jours fin de mois" <?= ($_POST['conditions_paiement'] ?? '') === '30 jours fin de mois' ? 'selected' : '' ?>>30 jours fin de mois</option>
                                <option value="45 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '45 jours net' ? 'selected' : '' ?>>45 jours net</option>
                                <option value="60 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '60 jours net' ? 'selected' : '' ?>>60 jours net</option>
                                <option value="Virement avant expédition" <?= ($_POST['conditions_paiement'] ?? '') === 'Virement avant expédition' ? 'selected' : '' ?>>Virement avant expédition</option>
                                <option value="Lettre de crédit" <?= ($_POST['conditions_paiement'] ?? '') === 'Lettre de crédit' ? 'selected' : '' ?>>Lettre de crédit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="fournisseurs.php" class="btn-border">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Créer le fournisseur
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
        .supplier-form {
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
    </style>

    <script>
        // Validation en temps réel du SIRET
        document.getElementById('siret').addEventListener('input', function() {
            const siret = this.value.replace(/\D/g, ''); // Garder seulement les chiffres
            this.value = siret;
            
            if (siret.length > 0 && siret.length !== 14) {
                this.style.borderColor = '#e74c3c';
            } else if (siret.length === 14) {
                this.style.borderColor = '#27ae60';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });

        // Auto-complétion ville/code postal français
        document.getElementById('code_postal').addEventListener('input', function() {
            const codePostal = this.value;
            if (codePostal.length === 5 && /^\d{5}$/.test(codePostal)) {
                // Ici on pourrait appeler une API pour récupérer la ville
                // Pour simplifier, on fait juste une validation visuelle
                this.style.borderColor = '#27ae60';
            }
        });

        // Validation du formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const email = document.getElementById('email').value.trim();
            const siret = document.getElementById('siret').value.trim();
            
            if (!nom) {
                alert('Le nom du fournisseur est obligatoire');
                e.preventDefault();
                return;
            }
            
            if (email && !email.includes('@')) {
                alert('L\'email n\'est pas valide');
                e.preventDefault();
                return;
            }
            
            if (siret && (siret.length !== 14 || !/^\d{14}$/.test(siret))) {
                alert('Le SIRET doit contenir exactement 14 chiffres');
                e.preventDefault();
                return;
            }
        });

        // Suggestions basées sur la spécialité
        document.getElementById('specialite_mode').addEventListener('change', function() {
            const specialite = this.value;
            const delaiInput = document.getElementById('delais_livraison');
            const conditionsSelect = document.getElementById('conditions_paiement');
            
            // Suggestions de délais selon la spécialité
            switch(specialite) {
                case 'Import international':
                    delaiInput.value = '21';
                    break;
                case 'Confection sur mesure':
                    delaiInput.value = '14';
                    break;
                case 'Prêt-à-porter haut de gamme':
                    delaiInput.value = '10';
                    break;
                case 'Textiles et matières premières':
                    delaiInput.value = '5';
                    break;
                default:
                    delaiInput.value = '7';
            }
        });
    </script>
</body>
</html>