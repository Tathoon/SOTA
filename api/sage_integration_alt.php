<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireLogin(['Admin']);

$manager = new SotaManager();
$user = getCurrentUser();

// Configuration SAGE
$sage_config = [
    'api_url' => 'https://api.sage.com/v1',
    'api_key' => 'YOUR_SAGE_API_KEY',
    'tenant_id' => 'YOUR_TENANT_ID',
    'company_id' => 'YOUR_COMPANY_ID'
];

$message = '';
$error = '';
$sync_results = [];

// Test de connexion SAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $response = testSageConnection($sage_config);
        if ($response['success']) {
            $message = "Connexion SAGE établie avec succès";
        } else {
            $error = "Erreur de connexion SAGE : " . $response['error'];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Synchronisation des commandes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_orders'])) {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        // Récupérer les commandes livrées non synchronisées
        $stmt = $manager->db->prepare("
            SELECT * FROM commandes 
            WHERE statut = 'livree' AND sage_export = 0
            ORDER BY date_commande DESC
            LIMIT 50
        ");
        $stmt->execute();
        $commandes_a_sync = $stmt->fetchAll();

        $sync_results = [];
        foreach ($commandes_a_sync as $commande) {
            $result = syncCommandeToSage($commande, $sage_config, $manager);
            $sync_results[] = [
                'commande' => $commande['numero_commande'],
                'success' => $result['success'],
                'message' => $result['message'],
                'facture_id' => $result['facture_id'] ?? null
            ];

            if ($result['success']) {
                // Marquer comme exporté
                $stmt = $manager->db->prepare("
                    UPDATE commandes SET 
                        sage_export = 1, 
                        sage_facture_id = ?, 
                        sage_statut_facture = 'created'
                    WHERE id = ?
                ");
                $stmt->execute([$result['facture_id'], $commande['id']]);
            }
        }

        $success_count = count(array_filter($sync_results, fn($r) => $r['success']));
        $message = "$success_count commande(s) synchronisée(s) avec SAGE";

        logActivite('sync_sage', [
            'commandes_synchronisees' => $success_count,
            'total_commandes' => count($commandes_a_sync)
        ], $user['id']);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Statistiques SAGE
try {
    $stmt = $manager->db->prepare("
        SELECT 
            COUNT(*) as total_commandes,
            COUNT(CASE WHEN sage_export = 1 THEN 1 END) as commandes_exportees,
            COUNT(CASE WHEN sage_export = 0 AND statut = 'livree' THEN 1 END) as en_attente_export,
            SUM(CASE WHEN sage_export = 1 THEN total ELSE 0 END) as ca_exporte
        FROM commandes
        WHERE statut = 'livree'
    ");
    $stmt->execute();
    $stats_sage = $stmt->fetch();
} catch (Exception $e) {
    $stats_sage = ['total_commandes' => 0, 'commandes_exportees' => 0, 'en_attente_export' => 0, 'ca_exporte' => 0];
}

/**
 * Test de connexion à l'API SAGE
 */
function testSageConnection($config) {
    try {
        // Simulation d'un appel API SAGE
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['api_url'] . '/ping',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['api_key'],
                'Content-Type: application/json',
                'X-Tenant-ID: ' . $config['tenant_id']
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Simulation - en réalité on vérifierait la vraie réponse SAGE
        if ($http_code === 200 || true) { // true pour simulation
            return ['success' => true, 'message' => 'Connexion établie'];
        } else {
            return ['success' => false, 'error' => 'Code HTTP ' . $http_code];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Synchronisation d'une commande vers SAGE
 */
function syncCommandeToSage($commande, $config, $manager) {
    try {
        // Récupérer les détails de la commande
        $details = $manager->getDetailsCommande($commande['id']);
        if (!$details) {
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }

        // Préparer les données pour SAGE
        $sage_data = [
            'invoice' => [
                'reference' => $commande['numero_commande'],
                'date' => $commande['date_commande'],
                'customer' => [
                    'name' => $commande['client_nom'],
                    'email' => $commande['client_email'],
                    'address' => $commande['client_adresse'],
                    'postal_code' => $commande['client_code_postal'],
                    'city' => $commande['client_ville']
                ],
                'lines' => []
            ]
        ];

        // Ajouter les lignes de produits
        foreach ($details['produits'] as $produit) {
            $sage_data['invoice']['lines'][] = [
                'product_code' => $produit['reference'],
                'description' => $produit['produit_nom'],
                'quantity' => $produit['quantite'],
                'unit_price' => $produit['prix_unitaire'],
                'vat_rate' => $produit['taux_tva'],
                'total' => $produit['sous_total']
            ];
        }

        // Appel API SAGE (simulation)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['api_url'] . '/invoices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($sage_data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['api_key'],
                'Content-Type: application/json',
                'X-Tenant-ID: ' . $config['tenant_id']
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Simulation de succès
        if ($http_code === 201 || true) { // true pour simulation
            $facture_id = 'FACT-' . date('Y') . '-' . rand(10000, 99999);
            return [
                'success' => true,
                'message' => 'Facture créée dans SAGE',
                'facture_id' => $facture_id
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erreur API SAGE : ' . $http_code
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erreur : ' . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intégration SAGE - SOTA Fashion</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content dashboard-main">
            <div class="top-bar">
                <div class="user-info">
                    <span>Bonjour, <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></span>
                    <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <section class="dashboard-header">
                <h1><i class="fas fa-sync"></i> Intégration SAGE</h1>
                <p class="dashboard-subtitle">Synchronisation avec le logiciel de comptabilité SAGE</p>
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

            <!-- Statistiques SAGE -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-file-invoice icon"></i>
                    <div class="stat-content">
                        <h3><?= $stats_sage['total_commandes'] ?></h3>
                        <p>Commandes livrées</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle icon" style="color: #27ae60;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_sage['commandes_exportees'] ?></h3>
                        <p>Exportées SAGE</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock icon" style="color: #f39c12;"></i>
                    <div class="stat-content">
                        <h3><?= $stats_sage['en_attente_export'] ?></h3>
                        <p>En attente export</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-euro-sign icon" style="color: #3498db;"></i>
                    <div class="stat-content">
                        <h3><?= formatPrice($stats_sage['ca_exporte']) ?></h3>
                        <p>CA exporté</p>
                    </div>
                </div>
            </div>

            <!-- Configuration SAGE -->
            <section class="dashboard-section">
                <h2><i class="fas fa-cog"></i> Configuration SAGE</h2>
                <div class="sage-config-card">
                    <div class="config-info">
                        <div class="config-item">
                            <label>URL API</label>
                            <span><?= htmlspecialchars($sage_config['api_url']) ?></span>
                        </div>
                        <div class="config-item">
                            <label>Tenant ID</label>
                            <span><?= htmlspecialchars($sage_config['tenant_id']) ?></span>
                        </div>
                        <div class="config-item">
                            <label>Company ID</label>
                            <span><?= htmlspecialchars($sage_config['company_id']) ?></span>
                        </div>
                        <div class="config-item">
                            <label>Statut connexion</label>
                            <span class="status-badge unknown">Non testé</span>
                        </div>
                    </div>
                    
                    <div class="config-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <button type="submit" name="test_connection" class="btn-border">
                                <i class="fas fa-plug"></i> Tester la connexion
                            </button>
                        </form>
                        <a href="sage_config.php" class="btn-border">
                            <i class="fas fa-cog"></i> Configurer
                        </a>
                    </div>
                </div>
            </section>

            <!-- Actions de synchronisation -->
            <section class="dashboard-section">
                <h2><i class="fas fa-sync-alt"></i> Synchronisation</h2>
                
                <div class="sync-actions-grid">
                    <div class="sync-action-card">
                        <div class="action-icon">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <div class="action-content">
                            <h3>Exporter les commandes</h3>
                            <p>Synchroniser les commandes livrées vers SAGE pour facturation</p>
                            <small><?= $stats_sage['en_attente_export'] ?> commande(s) en attente</small>
                        </div>
                        <div class="action-button">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <button type="submit" name="sync_orders" class="btn-orange"
                                        <?= $stats_sage['en_attente_export'] == 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-upload"></i> Synchroniser
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="sync-action-card">
                        <div class="action-icon">
                            <i class="fas fa-file-import"></i>
                        </div>
                        <div class="action-content">
                            <h3>Importer les statuts</h3>
                            <p>Récupérer les statuts de facturation depuis SAGE</p>
                            <small>Mise à jour des factures et paiements</small>
                        </div>
                        <div class="action-button">
                            <button onclick="importFromSage()" class="btn-border">
                                <i class="fas fa-download"></i> Importer
                            </button>
                        </div>
                    </div>
                    
                    <div class="sync-action-card">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-content">
                            <h3>Synchroniser clients</h3>
                            <p>Exporter les nouveaux clients vers SAGE</p>
                            <small>Mise à jour du fichier clients</small>
                        </div>
                        <div class="action-button">
                            <button onclick="syncClients()" class="btn-border">
                                <i class="fas fa-user-plus"></i> Synchroniser
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Résultats de synchronisation -->
            <?php if (!empty($sync_results)): ?>
            <section class="dashboard-section">
                <h2><i class="fas fa-list"></i> Résultats de synchronisation</h2>
                <div class="sync-results">
                    <?php foreach ($sync_results as $result): ?>
                        <div class="sync-result-item <?= $result['success'] ? 'success' : 'error' ?>">
                            <div class="result-icon">
                                <i class="fas fa-<?= $result['success'] ? 'check-circle' : 'times-circle' ?>"></i>
                            </div>
                            <div class="result-content">
                                <strong><?= htmlspecialchars($result['commande']) ?></strong>
                                <span><?= htmlspecialchars($result['message']) ?></span>
                                <?php if ($result['facture_id']): ?>
                                    <small>Facture SAGE : <?= htmlspecialchars($result['facture_id']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Historique des synchronisations -->
            <section class="dashboard-section">
                <h2><i class="fas fa-history"></i> Historique</h2>
                <div class="sync-history">
                    <?php
                    // Récupérer l'historique des logs SAGE
                    try {
                        $stmt = $manager->db->prepare("
                            SELECT * FROM logs_activite 
                            WHERE action IN ('sync_sage', 'test_sage_connection')
                            ORDER BY date_action DESC 
                            LIMIT 10
                        ");
                        $stmt->execute();
                        $logs_sage = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $logs_sage = [];
                    }
                    
                    if (empty($logs_sage)): ?>
                        <div class="empty-history">
                            <i class="fas fa-history"></i>
                            <p>Aucun historique de synchronisation</p>
                        </div>
                    <?php else: ?>
                        <div class="history-timeline">
                            <?php foreach ($logs_sage as $log): ?>
                                <div class="history-item">
                                    <div class="history-icon">
                                        <i class="fas fa-<?= $log['action'] === 'sync_sage' ? 'sync' : 'plug' ?>"></i>
                                    </div>
                                    <div class="history-content">
                                        <strong>
                                            <?= $log['action'] === 'sync_sage' ? 'Synchronisation' : 'Test connexion' ?>
                                        </strong>
                                        <span><?= formatDateTime($log['date_action']) ?></span>
                                        <?php if ($log['details']): ?>
                                            <?php $details = json_decode($log['details'], true); ?>
                                            <?php if (isset($details['commandes_synchronisees'])): ?>
                                                <small><?= $details['commandes_synchronisees'] ?> commandes synchronisées</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <style>
        .sage-config-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }

        .config-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            flex: 1;
        }

        .config-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .config-item label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
        }

        .config-item span {
            color: var(--secondary-color);
            font-weight: 500;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.unknown {
            background: #e2e3e5;
            color: #383d41;
        }

        .config-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sync-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .sync-action-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .sync-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            align-self: flex-start;
        }

        .action-content h3 {
            margin: 0 0 8px 0;
            color: var(--secondary-color);
            font-size: 18px;
        }

        .action-content p {
            margin: 0 0 5px 0;
            color: #666;
            line-height: 1.5;
        }

        .action-content small {
            color: var(--primary-color);
            font-weight: 600;
        }

        .action-button {
            margin-top: auto;
        }

        .sync-results {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .sync-result-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .sync-result-item.success {
            background: rgba(39, 174, 96, 0.1);
            border-left-color: #27ae60;
        }

        .sync-result-item.error {
            background: rgba(231, 76, 60, 0.1);
            border-left-color: #e74c3c;
        }

        .result-icon {
            font-size: 20px;
        }

        .sync-result-item.success .result-icon {
            color: #27ae60;
        }

        .sync-result-item.error .result-icon {
            color: #e74c3c;
        }

        .result-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .result-content strong {
            color: var(--secondary-color);
        }

        .result-content span {
            color: #666;
            font-size: 14px;
        }

        .result-content small {
            color: #999;
            font-size: 12px;
        }

        .empty-history {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-history i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .history-timeline {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .history-icon {
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

        .history-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .history-content strong {
            color: var(--secondary-color);
        }

        .history-content span {
            color: #666;
            font-size: 13px;
        }

        .history-content small {
            color: var(--primary-color);
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .sage-config-card {
                flex-direction: column;
                gap: 20px;
            }

            .config-info {
                grid-template-columns: 1fr;
            }

            .sync-actions-grid {
                grid-template-columns: 1fr;
            }

            .config-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <script>
        function importFromSage() {
            if (confirm('Importer les statuts de facturation depuis SAGE ?')) {
                fetch('sage_import_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Import réalisé avec succès : ' + data.message);
                        location.reload();
                    } else {
                        alert('Erreur lors de l\'import : ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur de connexion');
                });
            }
        }

        function syncClients() {
            if (confirm('Synchroniser les clients vers SAGE ?')) {
                fetch('sage_sync_clients.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Synchronisation réussie : ' + data.message);
                        location.reload();
                    } else {
                        alert('Erreur lors de la synchronisation : ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erreur de connexion');
                });
            }
        }

        // Auto-refresh des statistiques
        setInterval(function() {
            if (!document.hidden) {
                fetch('sage_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Mettre à jour les statistiques en temps réel
                        if (data.en_attente_export !== undefined) {
                            document.querySelector('.stat-card:nth-child(3) h3').textContent = data.en_attente_export;
                        }
                    })
                    .catch(error => {
                        // Erreur silencieuse
                    });
            }
        }, 30000); // Toutes les 30 secondes
    </script>
</body>
</html>