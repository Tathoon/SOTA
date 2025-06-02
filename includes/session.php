<?php
session_start();

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
            echo "<h1>403 - Accès refusé</h1><p>Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>";
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
        'role' => $_SESSION['user_role'] ?? ''
    ];
}

/**
 * Déconnecte et redirige
 */
function logout() {
    session_destroy();
    header('Location: /sota/login.php');
    exit();
}
