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
            // Page d'erreur de permissions avec style
            http_response_code(403);
            echo "<!DOCTYPE html>
            <html lang='fr'>
            <head>
                <meta charset='UTF-8'>
                <title>Accès refusé - SOTA Fashion</title>
                <link rel='stylesheet' href='/assets/css/style.css'>
                <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'>
            </head>
            <body class='dashboard-body'>
                <div class='container' style='display: flex; align-items: center; justify-content: center; min-height: 100vh;'>
                    <div style='text-align: center; background: white; padding: 50px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px;'>
                        <i class='fas fa-lock' style='font-size: 4em; color: #e74c3c; margin-bottom: 20px;'></i>
                        <h1 style='color: #e74c3c; margin-bottom: 20px;'>403 - Accès refusé</h1>
                        <p style='color: #666; margin-bottom: 15px;'>Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
                        <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                            <p style='margin: 0; font-size: 0.9em;'><strong>Rôle requis :</strong> " . implode(', ', $roles) . "</p>
                            <p style='margin: 5px 0 0 0; font-size: 0.9em;'><strong>Votre rôle :</strong> " . ($userRole ?: 'Aucun') . "</p>
                        </div>
                        <a href='/dashboard.php' class='btn-orange' style='text-decoration: none; margin-right: 10px;'>
                            <i class='fas fa-home'></i> Retour au tableau de bord
                        </a>
                        <a href='/logout.php' class='btn-border' style='text-decoration: none;'>
                            <i class='fas fa-sign-out-alt'></i> Se déconnecter
                        </a>
                        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                            <h4 style='color: #333; margin-bottom: 10px;'>Demander l'accès</h4>
                            <p style='color: #666; font-size: 0.85em;'>Contactez votre administrateur système pour obtenir les permissions nécessaires.</p>
                        </div>
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
 * Vérifie les permissions pour une action spécifique
 */
function hasPermission($action, $resource = null) {
    $userRole = $_SESSION['user_role'] ?? '';
    
    // Admin a tous les droits
    if ($userRole === 'Admin') {
        return true;
    }
    
    // Matrice des permissions par rôle et action
    $permissions = [
        'Gérant' => [
            'view_products' => true,
            'create_products' => true,
            'edit_products' => true,
            'delete_products' => true,
            'view_suppliers' => true,
            'create_suppliers' => true,
            'edit_suppliers' => true,
            'delete_suppliers' => true,
            'view_categories' => true,
            'create_categories' => true,
            'edit_categories' => true,
            'delete_categories' => true,
            'view_stocks' => true,
            'view_orders' => true,
            'view_deliveries' => true,
            'view_reports' => true,
        ],
        'Commercial' => [
            'view_products' => true,
            'view_stocks' => true,
            'view_categories' => true,
            'view_orders' => true,
            'create_orders' => true,
            'edit_orders' => true,
            'delete_orders' => true,
            'view_deliveries' => true,
            'view_reports' => true, // Limité aux rapports de ventes
        ],
        'Préparateur' => [
            'view_products' => true,
            'view_stocks' => true,
            'create_stock_movements' => true,
            'edit_stock_movements' => true,
            'view_orders' => true,
            'prepare_orders' => true,
            'view_deliveries' => true,
            'create_deliveries' => true,
            'view_reports' => true, // Limité aux rapports de stocks
        ],
        'Livreur' => [
            'view_orders' => true, // Lecture seule
            'view_deliveries' => true,
            'create_deliveries' => true,
            'edit_deliveries' => true,
            'update_delivery_status' => true,
        ]
    ];
    
    return isset($permissions[$userRole][$action]) && $permissions[$userRole][$action];
}

/**
 * Vérifie si l'utilisateur peut supprimer un élément
 */
function canDelete($resource) {
    $userRole = $_SESSION['user_role'] ?? '';
    
    $deletePermissions = [
        'Admin' => ['products', 'suppliers', 'categories', 'orders', 'users'],
        'Gérant' => ['products', 'suppliers', 'categories'],
        'Commercial' => ['orders'],
        'Préparateur' => [],
        'Livreur' => []
    ];
    
    return isset($deletePermissions[$userRole]) && in_array($resource, $deletePermissions[$userRole]);
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
 * Retourne les permissions de l'utilisateur actuel
 */
function getCurrentUserPermissions() {
    $userRole = $_SESSION['user_role'] ?? '';
    
    return [
        'can_manage_products' => hasPermission('create_products'),
        'can_manage_suppliers' => hasPermission('create_suppliers'),
        'can_manage_stocks' => hasPermission('create_stock_movements'),
        'can_manage_orders' => hasPermission('create_orders'),
        'can_manage_deliveries' => hasPermission('create_deliveries'),
        'can_manage_users' => hasRole('Admin'),
        'can_view_reports' => hasPermission('view_reports'),
        'can_sync_sage' => hasRole('Admin'),
        'is_admin' => hasRole('Admin'),
        'is_manager' => hasRole('Gérant'),
        'is_commercial' => hasRole('Commercial'),
        'is_warehouse' => hasRole('Préparateur'),
        'is_delivery' => hasRole('Livreur')
    ];
}

/**
 * Déconnecte et redirige
 */
function logout() {
    session_destroy();
    header('Location: /login.php?message=' . urlencode('Vous avez été déconnecté avec succès'));
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
 * Log une activité utilisateur
 */
function logActivity($action, $details = [], $user_id = null) {
    if (!$user_id) {
        $user = getCurrentUser();
        $user_id = $user['id'];
    }
    
    try {
        require_once __DIR__ . '/functions.php';
        $manager = new SotaManager();
        
        $stmt = $manager->db->prepare("
            INSERT INTO logs_activite (action, details, utilisateur_id, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $action,
            json_encode($details),
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Erreur log activité: " . $e->getMessage());
    }
}

/**
 * Middleware de permissions pour les pages sensibles
 */
function requirePermission($permission, $resource = null) {
    if (!hasPermission($permission, $resource)) {
        requireLogin(); // Au cas où l'utilisateur ne serait pas connecté
        
        // Si connecté mais sans permission
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <title>Permission insuffisante - SOTA Fashion</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body class='dashboard-body'>
            <div style='text-align: center; padding: 50px;'>
                <h1 style='color: #e74c3c;'>Permission insuffisante</h1>
                <p>Vous n'avez pas l'autorisation d'effectuer cette action.</p>
                <p><strong>Action requise :</strong> $permission</p>
                <a href='/dashboard.php' class='btn-orange'>← Retour au tableau de bord</a>
            </div>
        </body>
        </html>";
        exit();
    }
}
?>