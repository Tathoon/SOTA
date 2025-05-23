<?php
session_start();

// Récupération du formulaire
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Infos AD
$ad_server = "ldaps://192.168.100.6"; // IP ou FQDN de ton AD, le "ldaps://" force le SSL
$ad_dn = "DC=fashion,DC=local";       // Distinguised Name de ta forêt/domaine
$ad_user_suffix = "@fashion.local";   // Le suffixe UPN de tes users AD

if (!$username || !$password) {
    header('Location: login.html?error=empty');
    exit();
}

$ldap = ldap_connect($ad_server, 636);
if (!$ldap) {
    die("Impossible de se connecter à l'AD.");
}

// Optionnel : forcer le TLS/SSL, désactiver les checks pour self-signed (à éviter en prod !)
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

// Vérification du login (bind)
$login = $username . $ad_user_suffix;
$bind = @ldap_bind($ldap, $login, $password);

if ($bind) {
    // Auth OK : on récupère plus d'info si besoin
    $filter = "(sAMAccountName=$username)";
    $attributes = ["cn", "mail"];
    $result = ldap_search($ldap, $ad_dn, $filter, $attributes);
    $entries = ldap_get_entries($ldap, $result);

    $_SESSION['username'] = $entries[0]['cn'][0] ?? $username;
    $_SESSION['mail'] = $entries[0]['mail'][0] ?? '';
    // Redirection post-login
    header("Location: dashboard.php");
    exit();
} else {
    // Auth FAIL
    echo "<script>alert('Identifiant ou mot de passe incorrect.'); window.location.href='login.html';</script>";
    exit();
}
?>
