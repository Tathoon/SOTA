<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin']);

$manager = new SotaManager();
$user = getCurrentUser();

// Synchronisation LDAP si demandée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_ldap'])) {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        // Simulation de synchronisation LDAP
        $ldap_users = [
            ['username' => 'pierre.dupont', 'prenom' => 'Pierre', 'nom' => 'Dupont', 'email' => 'pierre.dupont@fashionchic.local', 'role' => 'Admin'],
            ['username' => 'sophie.martin', 'prenom' => 'Sophie', 'nom' => 'Martin', 'email' => 'sophie.martin@fashionchic.local', 'role' => 'Gérant'],
            ['username' => 'julie.bernard', 'prenom' => 'Julie', 'nom' => 'Bernard', 'email' => 'julie.bernard@fashionchic.local', 'role' => 'Commercial'],
            ['username' => 'thomas.durand', 'prenom' => 'Thomas', 'nom' => 'Durand', 'email' => 'thomas.durand@fashionchic.local', 'role' => 'Commercial'],
            ['username' => 'maxime.roux', 'prenom' => 'Maxime', 'nom' => 'Roux', 'email' => 'maxime.roux@fashionchic.local', 'role' => 'Préparateur'],
            ['username' => 'camille.moreau', 'prenom' => 'Camille', 'nom' => 'Moreau', 'email' => 'camille.moreau@fashionchic.local', 'role' => 'Préparateur'],
            ['username' => 'emma.petit', 'prenom' => 'Emma', 'nom' => 'Petit', 'email' => 'emma.petit@fashionchic.local', 'role' => 'Livreur']
        ];

        $synced_count = 0;
        foreach ($ldap_users as $ldap_user) {
            if ($manager->syncUtilisateurLDAP($ldap_user)) {
                $synced_count++;
            }
        }

        $message = "$synced_count utilisateur(s) synchronisé(s) depuis LDAP";
        
        logActivite('sync_ldap', [
            'utilisateurs_synchronises' => $synced_count
        ], $user['id']);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des utilisateurs
$utilisateurs = $manager->getUtilisateursLDAP();

// Statistiques des utilisateurs
$stats = [
    'total' => count($utilisateurs),
    'actifs' => count(array_filter($utilisateurs, fn($u) => $u['actif'] == 1)),
    'admins' => count(array_filter($utilisateurs, fn($u) => $u['role'] === 'Admin')),
    'connectes_recent' => count(array_filter($utilisateurs, fn($u) => $u['derniere_connexion'] && strtotime($u['derniere_connexion']) > strtotime('-7 days')))
];

$message = $_GET['message'] ?? $message ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - SOTA Fashion</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container dashboard-container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="main-content dashboard-main">
            <div class="top-bar">
                <div class="user-info">
                    <span>Bonjour, <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></span>
                    <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <a href="../../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h1><i class="fas fa-users"></i> Gestion des utilisateurs</h1>
                <p class="dashboard-subtitle">Utilisateurs Active Directory - <?= count($utilisateurs) ?> comptes</p>
            </section>

            <?php if ($message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques utilisateurs -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Utilisateurs total</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-check icon" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['actifs'] ?></h3>
                        <p>Comptes actifs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-shield icon" style="color: #e74c3c;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['admins'] ?></h3>
                        <p>Administrateurs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #3498db;"></i>
                    <div class="stat-content">
                        <h3><?= $stats['connectes_recent'] ?></h3>
                        <p>Connectés 7j</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <section class="dashboard-section">
                <div class="ldap-sync-section">
                    <div class="sync-info">
                        <h3><i class="fas fa-sync"></i> Synchronisation LDAP</h3>
                        <p>Synchronisez les utilisateurs depuis Active Directory fashionchic.local</p>
                        <small>Dernière synchronisation : <?= $utilisateurs ? formatDateTime($utilisateurs[0]['derniere_sync']) : 'Jamais' ?></small>
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" name="sync_ldap" class="btn-orange">
                            <i class="fas fa-sync"></i> Synchroniser LDAP
                        </button>
                    </form>
                </div>
            </section>

            <!-- Liste des utilisateurs -->
            <div class="users-container">
                <?php if (empty($utilisateurs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Aucun utilisateur synchronisé</h3>
                        <p>Synchronisez les utilisateurs depuis Active Directory pour commencer.</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <button type="submit" name="sync_ldap" class="btn-orange">
                                <i class="fas fa-sync"></i> Première synchronisation
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="users-table-container">
                        <table class="stock-table">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Rôle</th>
                                    <th>Email</th>
                                    <th>Dernière connexion</th>
                                    <th>Statut connexion</th>
                                    <th>Synchronisation</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilisateurs as $utilisateur): ?>
                                    <tr class="<?= $utilisateur['actif'] ? 'active' : 'inactive' ?>">
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div class="user-details">
                                                    <strong><?= htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?></strong>
                                                    <small><?= htmlspecialchars($utilisateur['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="role-badge <?= strtolower($utilisateur['role']) ?>">
                                                <?php
                                                $role_icons = [
                                                    'Admin' => 'fas fa-user-shield',
                                                    'Gérant' => 'fas fa-user-tie',
                                                    'Commercial' => 'fas fa-user-tag',
                                                    'Préparateur' => 'fas fa-user-cog',
                                                    'Livreur' => 'fas fa-truck'
                                                ];
                                                ?>
                                                <i class="<?= $role_icons[$utilisateur['role']] ?? 'fas fa-user' ?>"></i>
                                                <?= htmlspecialchars($utilisateur['role']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($utilisateur['email']): ?>
                                                <a href="mailto:<?= htmlspecialchars($utilisateur['email']) ?>" 
                                                   class="email-link">
                                                    <?= htmlspecialchars($utilisateur['email']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #999;">Non défini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($utilisateur['derniere_connexion']): ?>
                                                <div class="connection-info">
                                                    <span><?= formatDate($utilisateur['derniere_connexion']) ?></span>
                                                    <small><?= date('H:i', strtotime($utilisateur['derniere_connexion'])) ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="never-connected">Jamais connecté</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="connection-status <?= strtolower(str_replace(' ', '-', $utilisateur['statut_connexion'])) ?>">
                                                <?= htmlspecialchars($utilisateur['statut_connexion']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="sync-info">
                                                <span><?= formatDate($utilisateur['derniere_sync']) ?></span>
                                                <small><?= date('H:i', strtotime($utilisateur['derniere_sync'])) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button onclick="viewUserDetails(<?= $utilisateur['id'] ?>)" 
                                                        class="btn-border btn-small" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="viewUserLogs('<?= htmlspecialchars($utilisateur['username']) ?>')" 
                                                        class="btn-border btn-small" title="Voir logs">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                <?php if (!$utilisateur['actif']): ?>
                                                    <button onclick="toggleUserStatus(<?= $utilisateur['id'] ?>, 1)" 
                                                            class="btn-orange btn-small" title="Activer">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="toggleUserStatus(<?= $utilisateur['id'] ?>, 0)" 
                                                            class="btn-danger btn-small" title="Désactiver">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal détails utilisateur -->
    <div id="userDetailsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Détails utilisateur</h3>
                <button onclick="closeUserModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>

    <style>
        .users-container {
            margin: 0 30px;
        }

        .ldap-sync-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sync-info h3 {
            margin: 0 0 5px 0;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sync-info p {
            margin: 0 0 5px 0;
            color: #666;
        }

        .sync-info small {
            color: #999;
            font-style: italic;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .users-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-details strong {
            color: var(--secondary-color);
            font-size: 14px;
        }

        .user-details small {
            color: #666;
            font-size: 12px;
            font-family: monospace;
        }

        .role-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge.admin {
            background: #fee;
            color: #e74c3c;
        }

        .role-badge.gérant {
            background: #f0f8ff;
            color: #3498db;
        }

        .role-badge.commercial {
            background: #f0fff0;
            color: #27ae60;
        }

        .role-badge.préparateur {
            background: #fff8dc;
            color: #f39c12;
        }

        .role-badge.livreur {
            background: #f5f0ff;
            color: #9b59b6;
        }

        .email-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 13px;
        }

        .email-link:hover {
            text-decoration: underline;
        }

        .connection-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .connection-info span {
            font-size: 13px;
            color: var(--secondary-color);
        }

        .connection-info small {
            font-size: 11px;
            color: #666;
        }

        .never-connected {
            color: #999;
            font-style: italic;
            font-size: 13px;
        }

        .connection-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .connection-status.actif {
            background: #d4edda;
            color: #155724;
        }

        .connection-status.inactif {
            background: #f8d7da;
            color: #721c24;
        }

        .connection-status.jamais-connecté {
            background: #e2e3e5;
            color: #383d41;
        }

        .sync-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 12px;
        }

        .sync-info span {
            color: var(--secondary-color);
        }

        .sync-info small {
            color: #666;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .actions .btn-small {
            padding: 6px 8px;
            font-size: 12px;
        }

        tr.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .modal-body {
            padding: 20px;
        }

        @media (max-width: 768px) {
            .ldap-sync-section {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .users-table-container {
                overflow-x: auto;
            }

            .user-info {
                min-width: 150px;
            }
        }
    </style>

    <script>
        // Données des utilisateurs pour les modals
        const utilisateursData = <?= json_encode($utilisateurs) ?>;

        function viewUserDetails(userId) {
            const user = utilisateursData.find(u => u.id == userId);
            if (!user) return;

            const content = `
                <div class="user-detail-card">
                    <div class="user-header">
                        <div class="user-avatar-large">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h4>${user.prenom} ${user.nom}</h4>
                            <p>${user.username}</p>
                        </div>
                    </div>
                    
                    <div class="user-details-grid">
                        <div class="detail-item">
                            <label>Email</label>
                            <span>${user.email || 'Non défini'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Rôle</label>
                            <span>${user.role}</span>
                        </div>
                        <div class="detail-item">
                            <label>Statut</label>
                            <span>${user.actif ? 'Actif' : 'Inactif'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Dernière connexion</label>
                            <span>${user.derniere_connexion ? new Date(user.derniere_connexion).toLocaleString('fr-FR') : 'Jamais'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Dernière sync</label>
                            <span>${new Date(user.derniere_sync).toLocaleString('fr-FR')}</span>
                        </div>
                        <div class="detail-item">
                            <label>Statut connexion</label>
                            <span>${user.statut_connexion}</span>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('userDetailsContent').innerHTML = content;
            document.getElementById('userDetailsModal').style.display = 'flex';
        }

        function viewUserLogs(username) {
            window.open(`logs.php?user=${encodeURIComponent(username)}`, '_blank');
        }

        function toggleUserStatus(userId, newStatus) {
            const action = newStatus ? 'activer' : 'désactiver';
            if (confirm(`Êtes-vous sûr de vouloir ${action} cet utilisateur ?`)) {
                fetch('../../api/toggle_user_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        status: newStatus,
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la mise à jour : ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur de connexion');
                });
            }
        }

        function closeUserModal() {
            document.getElementById('userDetailsModal').style.display = 'none';
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('userDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserModal();
            }
        });

        // CSS pour le modal
        const modalStyles = `
            .user-detail-card {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            
            .user-header {
                display: flex;
                align-items: center;
                gap: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .user-avatar-large {
                width: 60px;
                height: 60px;
                background: var(--primary-color);
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
            }
            
            .user-header h4 {
                margin: 0;
                color: var(--secondary-color);
            }
            
            .user-header p {
                margin: 0;
                color: #666;
                font-family: monospace;
            }
            
            .user-details-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .detail-item {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-item label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                font-weight: 600;
                letter-spacing: 0.5px;
            }
            
            .detail-item span {
                color: var(--secondary-color);
                font-weight: 500;
            }
        `;
        
        const styleElement = document.createElement('style');
        styleElement.textContent = modalStyles;
        document.head.appendChild(styleElement);
    </script>
</body>
</html>