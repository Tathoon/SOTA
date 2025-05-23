<?php
session_start();

if (!empty($_SESSION['username'])) {
    header('Location: dashboard.php');
    exit();
}

$ldap_host = "ldaps://ldaps.fashionchic.local";
$ldap_port = 636;
$ldap_dn = "DC=fashionchic,DC=local";
putenv('LDAPTLS_CACERT=/etc/ssl/certs/CA-Root-fashionchic.crt');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $upn = $username . '@fashionchic.local';

        $ldap = ldap_connect($ldap_host, $ldap_port);
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        if (@ldap_bind($ldap, $upn, $password)) {
            $filter = "(sAMAccountName=$username)";
            $attrs = ["cn", "mail", "memberOf"];
            $result = ldap_search($ldap, $ldap_dn, $filter, $attrs);
            $entries = ldap_get_entries($ldap, $result);

            $_SESSION['username'] = $entries[0]['cn'][0] ?? $username;
            $_SESSION['mail'] = $entries[0]['mail'][0] ?? '';

            // Récupération du groupe principal (premier membre)
            $groupName = '';
            if (!empty($entries[0]['memberof'][0])) {
                if (preg_match('/CN=([^,]+)/', $entries[0]['memberof'][0], $matches)) {
                    $groupName = $matches[1];
                }
            }
            $_SESSION['group'] = $groupName;

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Identifiant ou mot de passe incorrect, ou erreur de connexion AD.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Fashion Chic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Fashion Chic</h1>
        <form id="loginForm" action="login.php" method="POST" autocomplete="off">
            <div class="input-group">
                <input type="text" name="username" id="username" required autocomplete="off" placeholder=" ">
                <label for="username">Nom d'utilisateur</label>
            </div>
            <div class="input-group">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password">Mot de passe</label>
            </div>
            <button type="submit">Se connecter</button>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
