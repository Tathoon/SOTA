<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Configuration LDAPS
$ldap_host = "ldaps://ldaps.fashionchic.local";
$ldap_port = 636;
$ldap_dn = "DC=fashionchic,DC=local";
putenv('LDAPTLS_CACERT=/etc/ssl/certs/CA-Root-fashionchic.crt');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $upn = $username . '@fashionchic.local';

    $ldap = ldap_connect($ldap_host, $ldap_port);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    if ($ldap && @ldap_bind($ldap, $upn, $password)) {
        // Recherche des infos
        $filter = "(sAMAccountName=$username)";
        $attributes = ['cn', 'givenName', 'sn', 'mail', 'memberOf'];
        $result = ldap_search($ldap, $ldap_dn, $filter, $attributes);
        $entries = ldap_get_entries($ldap, $result);

        if ($entries["count"] > 0) {
            $infos = $entries[0];

            $_SESSION['user_id'] = $username;
            $_SESSION['user_prenom'] = $infos['givenname'][0] ?? '';
            $_SESSION['user_nom'] = $infos['sn'][0] ?? '';
            $_SESSION['user_mail'] = $infos['mail'][0] ?? '';

            // Attribution du rôle selon le groupe
            $groupes = $infos['memberof'] ?? [];
            $role = 'Aucun';
            for ($i = 0; $i < ($groupes['count'] ?? 0); $i++) {
                $dn = $groupes[$i];
                if (strpos($dn, "CN=Admin") !== false) $role = "Admin";
                elseif (strpos($dn, "CN=Gérant") !== false) $role = "Gérant";
                elseif (strpos($dn, "CN=Commercial") !== false) $role = "Commercial";
                elseif (strpos($dn, "CN=Préparateur") !== false) $role = "Préparateur";
                elseif (strpos($dn, "CN=Livreur") !== false) $role = "Livreur";
            }

            $_SESSION['user_role'] = $role;

            // Mettre à jour la dernière connexion dans la base
            try {
                require_once 'includes/functions.php';
                $manager = new SotaManager();
                $manager->syncUtilisateurLDAP([
                    'username' => $username,
                    'prenom' => $infos['givenname'][0] ?? '',
                    'nom' => $infos['sn'][0] ?? '',
                    'email' => $infos['mail'][0] ?? '',
                    'role' => $role
                ]);
                
                // Mettre à jour la dernière connexion
                $stmt = $manager->db->prepare("UPDATE utilisateurs_ldap SET derniere_connexion = CURRENT_TIMESTAMP WHERE username = ?");
                $stmt->execute([$username]);
            } catch (Exception $e) {
                error_log("Erreur sync utilisateur: " . $e->getMessage());
            }

            header('Location: dashboard.php');
            exit();
        }
    }

    $error = "Échec de la connexion. Vérifiez vos identifiants.";
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
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body class="login-page">
    <div class="login-container">
        <form method="POST" class="login-form">
            <div class="login-header">
                <h2><i class="fas fa-tshirt"></i> SOTA Fashion</h2>
                <p>Système de gestion prêt-à-porter féminin</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Nom d'utilisateur" required autofocus>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Mot de passe" required>
            </div>

            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>

            <div class="login-footer">
                <p>Accès sécurisé</p>
                <small>Connectez-vous avec vos identifiants</small>
            </div>
        </form>
    </div>

    <style>
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #ff6b35;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 13px;
        }
        
        .login-footer small {
            display: block;
            margin-top: 5px;
            opacity: 0.8;
        }
    </style>
</body>
</html>