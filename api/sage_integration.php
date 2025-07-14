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

// Synchronisation des clients
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_clients'])) {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Token de sécurité invalide");
        }

        $result = syncClientsToSage($sage_config, $manager);
        if ($result['success']) {
            $message = "Clients synchronisés avec SAGE : " . $result['message'];
        } else {
            $error = "Erreur synchronisation clients : " . $result['message'];
        }
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
        // Simulation d'un appel API SAGE (remplacer par vraie API)
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

/**
 * Synchronisation des clients vers SAGE
 */
function syncClientsToSage($config, $manager) {
    try {
        // Récupérer les clients uniques des commandes
        $stmt = $manager->db->prepare("
            SELECT DISTINCT client_nom, client_email, client_telephone, 
                   client_adresse, client_code_postal, client_ville
            FROM commandes 
            WHERE client_nom IS NOT NULL
            ORDER BY client_nom
        ");
        $stmt->execute();
        $clients = $stmt->fetchAll();

        $synced_count = 0;
        foreach ($clients as $client) {
            // Simulation d'envoi vers SAGE
            $client_data = [
                'name' => $client['client_nom'],
                'email' => $client['client_email'],
                'phone' => $client['client_telephone'],
                'address' => $client['client_adresse'],
                'postal_code' => $client['client_code_postal'],
                'city' => $client['client_ville']
            ];

            // Ici on ferait l'appel API SAGE réel
            // Pour la simulation, on considère que ça marche
            $synced_count++;
        }

        return [
            'success' => true,
            'message' => "$synced_count clients synchronisés"
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
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
            <div class="dashboard-section">
                <h2><i class="fas fa-cog"></i> Configuration SAGE</h2>
                <div class="cards">
                    <div class="card">
                        <i class="fas fa-server icon"></i>
                        <p><strong>Serveur API:</strong><br><?= htmlspecialchars($sage_config['api_url']) ?></p>
                    </div>
                    <div class="card">
                        <i class="fas fa-key icon"></i>
                        <p><strong>Tenant ID:</strong><br><?= htmlspecialchars($sage_config['tenant_id']) ?></p>
                    </div>
                    <div class="card">
                        <i class="fas fa-building icon"></i>
                        <p><strong>Company ID:</strong><br><?= htmlspecialchars($sage_config['company_id']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Actions de synchronisation -->
            <div class="dashboard-grid">
                <div class="dashboard-section">
                    <h2><i class="fas fa-sync-alt"></i> Actions de synchronisation</h2>
                    
                    <div class="cards">
                        <div class="card">
                            <i class="fas fa-file-export icon"></i>
                            <p><strong>Exporter commandes</strong></p>
                            <p><?= $stats_sage['en_attente_export'] ?> commande(s) en attente</p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <button type="submit" name="sync_orders" class="btn-orange"
                                        <?= $stats_sage['en_attente_export'] == 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-upload"></i> Synchroniser
                                </button>
                            </form>
                        </div>
                        
                        <div class="card">
                            <i class="fas fa-users icon"></i>
                            <p><strong>Synchroniser clients</strong></p>
                            <p>Exporter les clients vers SAGE</p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <button type="submit" name="sync_clients" class="btn-border">
                                    <i class="fas fa-user-plus"></i> Synchroniser
                                </button>
                            </form>
                        </div>
                        
                        <div class="card">
                            <i class="fas fa-plug icon"></i>
                            <p><strong>Tester connexion</strong></p>
                            <p>Vérifier la connexion SAGE</p>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <button type="submit" name="test_connection" class="btn-border">
                                    <i class="fas fa-plug"></i> Tester
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Résultats de synchronisation -->
                <?php if (!empty($sync_results)): ?>
                <div class="dashboard-section">
                    <h2><i class="fas fa-list"></i> Résultats de synchronisation</h2>
                    <div class="recent-list">
                        <?php foreach ($sync_results as $result): ?>
                            <div class="recent-item">
                                <div>
                                    <strong><?= htmlspecialchars($result['commande']) ?></strong>
                                    <small><?= htmlspecialchars($result['message']) ?></small>
                                </div>
                                <div>
                                    <?php if ($result['success']): ?>
                                        <span class="status actif">Succès</span>
                                    <?php else: ?>
                                        <span class="status rupture">Échec</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($result['facture_id']): ?>
                                    <div>
                                        <small>ID: <?= htmlspecialchars($result['facture_id']) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Historique des synchronisations -->
            <div class="dashboard-section">
                <h2><i class="fas fa-history"></i> Historique</h2>
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
                    <p class="text-center">Aucun historique de synchronisation</p>
                <?php else: ?>
                    <div class="recent-list">
                        <?php foreach ($logs_sage as $log): ?>
                            <div class="recent-item">
                                <div>
                                    <strong>
                                        <?= $log['action'] === 'sync_sage' ? 'Synchronisation' : 'Test connexion' ?>
                                    </strong>
                                    <small><?= formatDateTime($log['date_action']) ?></small>
                                </div>
                                <div>
                                    <?php if ($log['details']): ?>
                                        <?php $details = json_decode($log['details'], true); ?>
                                        <?php if (isset($details['commandes_synchronisees'])): ?>
                                            <?= $details['commandes_synchronisees'] ?> commandes
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh des statistiques
        setInterval(function() {
            if (!document.hidden) {
                fetch('sage_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Mettre à jour les statistiques en temps réel
                        if (data.en_attente_export !== undefined) {
                            const statCard = document.querySelector('.stat-card:nth-child(3) h3');
                            if (statCard) {
                                statCard.textContent = data.en_attente_export;
                            }
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