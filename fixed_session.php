<?php
/**
 * Gestion des sessions avec chemins corrigés
 * includes/session.php
 */

// Inclure la configuration
require_once __DIR__ . '/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Oblige la connexion + (optionnel) filtrage par rôles
 */
function requireLogin(array $roles = []) {
    if (!isLoggedIn()) {
        // Utiliser un chemin absolu pour la redirection
        safe_redirect('login.php');
        exit();
    }

    if (!empty($roles)) {
        $userRole = $_SESSION['user_role'] ?? '';
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            
            // Calculer le chemin correct pour les assets selon le niveau de dossier
            $current_dir = dirname($_SERVER['SCRIPT_NAME']);
            $depth = substr_count($current_dir, '/') - 1;
            $css_path = str_repeat('../', max(0, $depth)) . 'assets/css/style.css';
            
            echo "<!DOCTYPE html>
            <html lang='fr'>
            <head>
                <meta charset='UTF-8'>
                <title>Accès refusé - SOTA Fashion</title>
                <link rel='stylesheet' href='$css_path'>
                <style>
                    .error-container {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        font-family: Arial, sans-serif;
                    }
                    .error-card {
                        background: white;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                        text-align: center;
                        max-width: 500px;
                    }
                    .error-icon {
                        font-size: 64px;
                        color: #e74c3c;
                        margin-bottom: 20px;
                    }
                    .error-title {
                        color: #e74c3c;
                        margin-bottom: 20px;
                        font-size: 28px;
                    }
                    .error-message {
                        color: #666;
                        margin-bottom: 10px;
                        line-height: 1.6;
                    }
                    .back-button {
                        display: inline-block;
                        background: #ff6b35;
                        color: white;
                        padding: 12px 24px;
                        text-decoration: none;
                        border-radius: 6px;
                        margin-top: 20px;
                        transition: background 0.3s;
                    }
                    .back-button:hover {
                        background: #e55a2e;
                    }
                </style>
            </head>
            <body>
                <div class='error-container'>
                    <div class='error-card'>
                        <div class='error-icon'>🚫</div>
                        <h1 class='error-title'>403 - Accès refusé</h1>
                        <p class='error-message'>Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
                        <p class='error-message'><strong>Rôle requis :</strong> " . implode(', ', $roles) . "</p>
                        <p class='error-message'><strong>Votre rôle :</strong> " . ($userRole ?: 'Aucun') . "</p>
                        <a href='" . url('dashboard.php') . "' class='back-button'>← Retour au tableau de bord</a>
                    </div>
                </div>
            </body>
            </html>";
            exit();
        }
    }
}

/**
 * Vérifie un rôle exact
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Vérifie plusieurs rôles
 */
function hasAnyRole($roles) {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $roles);
}

/**
 * Retourne les infos utilisateur courantes
 */
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nom' => $_SESSION['user_nom'] ?? '',
        'prenom' => $_SESSION['user_prenom'] ?? '',
        'email' => $_SESSION['user_mail'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'Aucun',
        'username' => $_SESSION['username'] ?? ''
    ];
}

/**
 * Déconnecte et redirige
 */
function logout() {
    session_destroy();
    safe_redirect('login.php');
    exit();
}

/**
 * Génère un token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Fonction pour débugger les chemins
 */
function debugPaths() {
    if (DEBUG_MODE) {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
        echo "<h4>Debug des chemins:</h4>";
        echo "<strong>BASE_PATH:</strong> " . (defined('BASE_PATH') ? BASE_PATH : 'Non défini') . "<br>";
        echo "<strong>BASE_URL:</strong> " . (defined('BASE_URL') ? BASE_URL : 'Non défini') . "<br>";
        echo "<strong>SCRIPT_NAME:</strong> " . $_SERVER['SCRIPT_NAME'] . "<br>";
        echo "<strong>REQUEST_URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";