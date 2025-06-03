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
    // Protection contre l'injection LDAP
    $escapedUsername = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
    $upn = $username . '@fashionchic.local';

    $ldap = ldap_connect($ldap_host, $ldap_port);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    if ($ldap && @ldap_bind($ldap, $upn, $password)) {
        // Recherche des infos
        $filter = "(sAMAccountName=$escapedUsername)";
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
    <title>Connexion</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <form method="POST" class="login-form">
            <h2 class="text-center">Connexion SOTA</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Nom d'utilisateur" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Mot de passe" required>
            </div>

            <button type="submit" class="login-button">Connexion</button>
        </form>
    </div>
</body>
</html>
