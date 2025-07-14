<?php
/**
 * Configuration globale pour corriger les erreurs de chemins
 * Créer ce fichier : includes/config.php
 */

// Configuration des chemins absolus
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes/');
define('ASSETS_PATH', BASE_PATH . '/assets/');
define('PAGES_PATH', BASE_PATH . '/pages/');

// Configuration de l'URL de base
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
$base_url = $protocol . $host . dirname(dirname($script_name));

define('BASE_URL', rtrim($base_url, '/'));
define('ASSETS_URL', BASE_URL . '/assets');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'sota');
define('DB_USER', 'root'); // Changez selon votre config
define('DB_PASS', '');     // Changez selon votre config
define('DB_CHARSET', 'utf8mb4');

// Configuration LDAP (si utilisé)
define('LDAP_HOST', 'localhost');
define('LDAP_PORT', 389);
define('LDAP_DN', 'dc=example,dc=com');

// Configuration SAGE
define('SAGE_API_URL', 'https://api.sage.com/v1');
define('SAGE_AUTH_URL', 'https://auth.sage.com/oauth/token');
define('SAGE_CLIENT_ID', 'YOUR_CLIENT_ID');
define('SAGE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');

// Mode debug
define('DEBUG_MODE', true); // Mettre à false en production

// Configuration des erreurs
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/logs/php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/**
 * Fonction pour inclure des fichiers de manière sécurisée
 */
function safe_include($file) {
    $full_path = INCLUDES_PATH . $file;
    if (file_exists($full_path)) {
        require_once $full_path;
        return true;
    } else {
        if (DEBUG_MODE) {
            die("Erreur: Fichier non trouvé - $full_path");
        }
        return false;
    }
}

/**
 * Fonction pour rediriger de manière sécurisée
 */
function safe_redirect($page) {
    $url = BASE_URL . '/' . ltrim($page, '/');
    header("Location: $url");
    exit;
}

/**
 * Fonction pour créer des URLs absolues
 */
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Fonction pour créer des URLs d'assets
 */
function asset($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}
?>