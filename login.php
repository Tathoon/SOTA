<?php
$ldap = ldap_connect("ldaps://192.168.100.6");
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

if (@ldap_bind($ldap, "user@fashion.local", "motdepasse")) {
    echo "Bind successful";
} else {
    echo "Bind failed";
}
?>
