<?php
session_start();

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/functions.php';

$error = '';
$message = $_GET['message'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Veuillez saisir votre nom d\'utilisateur et mot de passe';
    } else {
        try {
            $manager = new SotaManager();
            
            // Authentification simplifiée (à adapter selon votre système)
            $stmt = $manager->db->prepare("
                SELECT id, username, prenom, nom, email, role, actif 
                FROM utilisateurs_ldap 
                WHERE username = ? AND actif = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Dans un vrai système, vous vérifieriez le mot de passe ici
                // Pour la démo, on accepte tout mot de passe non vide
                
                // Mettre à jour la dernière connexion
                $stmt = $manager->db->prepare("
                    UPDATE utilisateurs_ldap 
                    SET derniere_connexion = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['nom'] = $user['nom'];
                
                // Log de connexion
                $stmt = $manager->db->prepare("
                    INSERT INTO logs_activite (action, details, utilisateur_id, ip_address) 
                    VALUES ('connexion', ?, ?, ?)
                ");
                $stmt->execute([
                    json_encode(['user' => $username]),
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Nom d\'utilisateur ou mot de passe incorrect';
            }
        } catch (Exception $e) {
            error_log("Erreur connexion: " . $e->getMessage());
            $error = 'Erreur de connexion. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SOTA Fashion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-tshirt"></i>
                    <h1>SOTA Fashion</h1>
                </div>
                <p>Système de gestion des stocks et commandes</p>
            </div>

            <?php if ($message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Nom d'utilisateur
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           required 
                           autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Votre nom d'utilisateur">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Mot de passe
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password"
                           placeholder="Votre mot de passe">
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>

            <div class="login-footer">
                <div class="demo-accounts">
                    <h4>Comptes de démonstration :</h4>
                    <div class="demo-list">
                        <div class="demo-account">
                            <strong>admin</strong> - Administrateur
                        </div>
                        <div class="demo-account">
                            <strong>manager</strong> - Gérant
                        </div>
                        <div class="demo-account">
                            <strong>commercial</strong> - Commercial
                        </div>
                        <div class="demo-account">
                            <strong>preparateur</strong> - Préparateur
                        </div>
                        <div class="demo-account">
                            <strong>livreur</strong> - Livreur
                        </div>
                    </div>
                    <small class="demo-note">
                        <i class="fas fa-info-circle"></i>
                        Utilisez n'importe quel mot de passe pour la démonstration
                    </small>
                </div>
            </div>
        </div>
    </div>

    <style>
        .login-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-