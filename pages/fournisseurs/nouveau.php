<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom' => trim($_POST['nom']),
            'siret' => !empty($_POST['siret']) ? trim($_POST['siret']) : null,
            'contact' => !empty($_POST['contact']) ? trim($_POST['contact']) : null,
            'telephone' => !empty($_POST['telephone']) ? trim($_POST['telephone']) : null,
            'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
            'adresse' => !empty($_POST['adresse']) ? trim($_POST['adresse']) : null,
            'ville' => !empty($_POST['ville']) ? trim($_POST['ville']) : null,
            'code_postal' => !empty($_POST['code_postal']) ? trim($_POST['code_postal']) : null,
            'delais_livraison' => (int)($_POST['delais_livraison'] ?? 7),
            'conditions_paiement' => !empty($_POST['conditions_paiement']) ? trim($_POST['conditions_paiement']) : null
        ];
        
        // Validation
        if (empty($data['nom'])) {
            throw new Exception("Le nom du fournisseur est obligatoire");
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email n'est pas valide");
        }
        
        if (!empty($data['siret'])) {
            $siret = str_replace(' ', '', $data['siret']);
            if (!preg_match('/^[0-9]{14}$/', $siret)) {
                throw new Exception("Le numéro SIRET doit contenir 14 chiffres");
            }
        }
        
        if ($manager->ajouterFournisseur($data)) {
            $message = "Fournisseur ajouté avec succès";
            $_POST = []; // Reset form
        } else {
            $error = "Erreur lors de l'ajout du fournisseur";
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
    <title>Nouveau fournisseur - SOTA</title>
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
                <h2><i class="fas fa-plus-circle"></i> Nouveau fournisseur</h2>
                <p class="dashboard-subtitle">Ajout d'un fournisseur au carnet d'adresses</p>
            </section>

            <?php if (!empty($message)): ?>
                <div class="success-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="supplier-form">
                <!-- Informations générales -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i> Informations générales
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom de l'entreprise *</label>
                            <input type="text" name="nom" id="nom" required class="form-control" 
                                   placeholder="Nom de l'entreprise" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="siret">Numéro SIRET</label>
                            <input type="text" name="siret" id="siret" class="form-control" 
                                   placeholder="12345678901234" maxlength="17"
                                   value="<?= htmlspecialchars($_POST['siret'] ?? '') ?>">
                            <small>14 chiffres (espaces automatiquement ajoutés)</small>
                        </div>
                    </div>
                </div>

                <!-- Contact principal -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-tie"></i> Contact principal
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="contact">Nom du contact</label>
                            <input type="text" name="contact" id="contact" class="form-control"
                                   placeholder="Prénom Nom" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" name="telephone" id="telephone" class="form-control"
                                   placeholder="01 23 45 67 89" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   placeholder="contact@entreprise.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Adresse
                    </h3>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="adresse">Adresse complète</label>
                        <textarea name="adresse" id="adresse" rows="3" class="form-control" 
                                  placeholder="Numéro, rue, complément d'adresse..."><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="code_postal">Code postal</label>
                            <input type="text" name="code_postal" id="code_postal" class="form-control" 
                                   pattern="[0-9]{5}" maxlength="5" placeholder="69000"
                                   value="<?= htmlspecialchars($_POST['code_postal'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ville">Ville</label>
                            <input type="text" name="ville" id="ville" class="form-control"
                                   placeholder="Lyon" value="<?= htmlspecialchars($_POST['ville'] ?? '') ?>">
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
                            <label for="delais_livraison">Délais de livraison (jours)</label>
                            <input type="number" name="delais_livraison" id="delais_livraison" 
                                   min="1" max="365" value="<?= $_POST['delais_livraison'] ?? 7 ?>" class="form-control">
                            <small>Nombre de jours entre la commande et la livraison</small>
                        </div>
                        <div class="form-group">
                            <label for="conditions_paiement">Conditions de paiement</label>
                            <select name="conditions_paiement" id="conditions_paiement" class="form-control">
                                <option value="">Sélectionner</option>
                                <option value="Comptant" <?= ($_POST['conditions_paiement'] ?? '') === 'Comptant' ? 'selected' : '' ?>>Comptant</option>
                                <option value="30 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '30 jours net' ? 'selected' : '' ?>>30 jours net</option>
                                <option value="45 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '45 jours net' ? 'selected' : '' ?>>45 jours net</option>
                                <option value="60 jours net" <?= ($_POST['conditions_paiement'] ?? '') === '60 jours net' ? 'selected' : '' ?>>60 jours net</option>
                                <option value="30 jours fin de mois" <?= ($_POST['conditions_paiement'] ?? '') === '30 jours fin de mois' ? 'selected' : '' ?>>30 jours fin de mois</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-orange">
                        <i class="fas fa-save"></i> Ajouter le fournisseur
                    </button>
                    <a href="fournisseurs.php" class="btn-border">
                        <i class="fas fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            </form>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Formatage automatique du SIRET
        const siretInput = document.getElementById('siret');
        siretInput.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '');
            if (value.length > 14) {
                value = value.substring(0, 14);
            }
            // Ajouter des espaces pour la lisibilité
            if (value.length >= 14) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{5})/, '$1 $2 $3 $4');
            }
            this.value = value;
        });

        // Validation en temps réel de l'email
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('blur', function() {
            if (this.value && !this.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                this.setCustomValidity('Veuillez entrer une adresse email valide');
                this.style.borderColor = '#e74c3c';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '';
            }
        });

        // Auto-complétion des villes françaises (simulation)
        const villeInput = document.getElementById('ville');
        const codePostalInput = document.getElementById('code_postal');
        
        const villes = {
            '69000': 'Lyon',
            '75000': 'Paris',
            '13000': 'Marseille',
            '59000': 'Lille',
            '31000': 'Toulouse',
            '44000': 'Nantes',
            '67000': 'Strasbourg',
            '33000': 'Bordeaux',
            '06000': 'Nice',
            '35000': 'Rennes'
        };

        codePostalInput.addEventListener('blur', function() {
            const cp = this.value;
            if (cp.length === 5 && villes[cp] && !villeInput.value) {
                villeInput.value = villes[cp];
            }
        });

        // Formatage du téléphone
        const telephoneInput = document.getElementById('telephone');
        telephoneInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
            }
            this.value = value;
        });

        // Confirmation avant soumission
        document.querySelector('.supplier-form').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value;
            if (!confirm(`Êtes-vous sûr de vouloir ajouter le fournisseur "${nom}" ?`)) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>