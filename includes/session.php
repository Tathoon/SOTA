<?php
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
        header('Location: /login.php');
        exit();
    }

    if (!empty($roles)) {
        $userRole = $_SESSION['user_role'] ?? '';
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            echo "<!DOCTYPE html>
            <html lang='fr'>
            <head>
                <meta charset='UTF-8'>
                <title>Accès refusé - SOTA Fashion</title>
                <link rel='stylesheet' href='/assets/css/style.css'>
            </head>
            <body>
                <div style='text-align: center; padding: 50px;'>
                    <h1 style='color: #e74c3c;'>403 - Accès refusé</h1>
                    <p>Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
                    <p>Rôle requis : " . implode(', ', $roles) . "</p>
                    <p>Votre rôle : " . ($userRole ?: 'Aucun') . "</p>
                    <a href='/dashboard.php' style='color: #ff6b35;'>← Retour au tableau de bord</a>
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
        'role' => $_SESSION['user_role'] ?? 'Aucun'
    ];
}

/**
 * Déconnecte et redirige
 */
function logout() {
    session_destroy();
    header('Location: /login.php');
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
?>