<?php
putenv('LDAPTLS_CACERT=/etc/ssl/certs/CA-Root-fashionchic.crt');

$ldap_host = "ldaps://ldaps.fashionchic.local";
$ldap_port = 636;

$ldap_user = "ldap@fashionchic.local";  
$ldap_pass = "Windows2022";        

// Connexion LDAP
$ldap = ldap_connect($ldap_host, $ldap_port);
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

// Tentative de bind
if (@ldap_bind($ldap, $ldap_user, $ldap_pass)) {
    echo "Bind successful";
} else {
    echo "Bind failed<br>";
    echo "LDAP error: " . ldap_error($ldap) . "<br>";
    echo "LDAP errno: " . ldap_errno($ldap) . "<br>";
}
?>
