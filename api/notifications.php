<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$manager = new SotaManager();
$user = getCurrentUser();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Récupérer les notifications pour l'utilisateur
            $notifications = getNotificationsForUser($manager, $user);
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;

        case 'mark_read':
            // Marquer une notification comme lue
            $notification_id = (int)($_POST['id'] ?? 0);
            if ($notification_id) {
                markNotificationAsRead($manager, $notification_id, $user['id']);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID manquant']);
            }
            break;

        case 'mark_all_read':
            // Marquer toutes les notifications comme lues
            markAllNotificationsAsRead($manager, $user['id']);
            echo json_encode(['success' => true]);
            break;

        case 'create':
            // Créer une nouvelle notification (pour les admins)
            if ($user['role'] !== 'Admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Accès refusé']);
                exit();
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $result = createNotification($manager, $data);
            echo json_encode($result);
            break;

        case 'check_alerts':
            // Vérifier les alertes automatiques
            $alerts = checkAutomaticAlerts($manager, $user);
            echo json_encode(['success' => true, 'alerts' => $alerts]);
            break;

        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Récupérer les notifications pour un utilisateur
 */
function getNotificationsForUser($manager, $user) {
    try {
        $stmt = $manager->db->prepare("
            SELECT * FROM notifications 
            WHERE (destinataire = ? OR destinataire = 'all' OR destinataire = ?)
            AND lu = 0
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$user['id'], $user['role']]);
        
        $notifications = $stmt->fetchAll();
        
        // Ajouter le temps relatif
        foreach ($notifications as &$notif) {
            $notif['temps_relatif'] = getTimeAgo($notif['created_at']);
            $notif['donnees'] = json_decode($notif['donnees'], true);
        }
        
        return $notifications;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($manager, $notification_id, $user_id) {
    $stmt = $manager->db->prepare("
        UPDATE notifications 
        SET lu = 1, date_lecture = CURRENT_TIMESTAMP 
        WHERE id = ? AND (destinataire = ? OR destinataire = 'all')
    ");
    return $stmt->execute([$notification_id, $user_id]);
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllNotificationsAsRead($manager, $user_id) {
    $stmt = $manager->db->prepare("
        UPDATE notifications 
        SET lu = 1, date_lecture = CURRENT_TIMESTAMP 
        WHERE lu = 0 AND (destinataire = ? OR destinataire = 'all')
    ");
    return $stmt->execute([$user_id]);
}

/**
 * Créer une nouvelle notification
 */
function createNotification($manager, $data) {
    try {
        $stmt = $manager->db->prepare("
            INSERT INTO notifications (type, destinataire, sujet, message, donnees)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['type'] ?? 'info',
            $data['destinataire'] ?? 'all',
            $data['sujet'] ?? '',
            $data['message'] ?? '',
            json_encode($data['donnees'] ?? [])
        ]);
        
        return ['success' => $result, 'id' => $manager->db->lastInsertId()];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Vérifier les alertes automatiques
 */
function checkAutomaticAlerts($manager, $user) {
    $alerts = [];
    
    try {
        // Alertes stock critique (pour Admin/Gérant/Préparateur)
        if (in_array($user['role'], ['Admin', 'Gérant', 'Préparateur'])) {
            $stmt = $manager->db->prepare("
                SELECT COUNT(*) as nb FROM produits 
                WHERE stock_actuel <= seuil_minimum AND actif = 1
            ");
            $stmt->execute();
            $stock_critique = $stmt->fetch()['nb'];

            if ($stock_critique > 0) {
                $alerts[] = [
                    'type' => 'stock_critique',
                    'level' => 'warning',
                    'message' => "$stock_critique produit(s) en stock critique",
                    'count' => $stock_critique,
                    'url' => '/pages/stocks/stocks.php?filtre=critique'
                ];
            }
        }

        // Commandes en retard (pour Admin/Commercial)
        if (in_array($user['role'], ['Admin', 'Commercial'])) {
            $stmt = $manager->db->prepare("
                SELECT COUNT(*) as nb FROM commandes 
                WHERE date_livraison_prevue < CURRENT_DATE 
                AND statut NOT IN ('livree', 'annulee')
            ");
            $stmt->execute();
            $commandes_retard = $stmt->fetch()['nb'];

            if ($commandes_retard > 0) {
                $alerts[] = [
                    'type' => 'livraison_retard',
                    'level' => 'error',
                    'message' => "$commandes_retard commande(s) en retard",
                    'count' => $commandes_retard,
                    'url' => '/pages/commandes/commandes.php'
                ];
            }
        }

        // Livraisons à préparer (pour Admin/Livreur/Préparateur)
        if (in_array($user['role'], ['Admin', 'Livreur', 'Préparateur'])) {
            $stmt = $manager->db->prepare("
                SELECT COUNT(*) as nb FROM livraisons 
                WHERE statut = 'planifiee' 
                AND date_prevue <= DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)
            ");
            $stmt->execute();
            $livraisons_demain = $stmt->fetch()['nb'];

            if ($livraisons_demain > 0) {
                $alerts[] = [
                    'type' => 'livraisons_demain',
                    'level' => 'info',
                    'message' => "$livraisons_demain livraison(s) prévue(s) demain",
                    'count' => $livraisons_demain,
                    'url' => '/pages/livraisons/livraisons.php'
                ];
            }
        }

        // Nouvelles commandes du jour (pour tous)
        $stmt = $manager->db->prepare("
            SELECT COUNT(*) as nb FROM commandes 
            WHERE DATE(created_at) = CURRENT_DATE
        ");
        $stmt->execute();
        $nouvelles_commandes = $stmt->fetch()['nb'];

        if ($nouvelles_commandes > 0) {
            $alerts[] = [
                'type' => 'nouvelles_commandes',
                'level' => 'info',
                'message' => "$nouvelles_commandes nouvelle(s) commande(s) aujourd'hui",
                'count' => $nouvelles_commandes,
                'url' => '/pages/commandes/commandes.php'
            ];
        }

    } catch (Exception $e) {
        error_log("Erreur checkAutomaticAlerts: " . $e->getMessage());
    }

    return $alerts;
}

/**
 * Calculer le temps relatif
 */
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'À l\'instant';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Il y a $minutes minute" . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Il y a $hours heure" . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "Il y a $days jour" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y', strtotime($datetime));
    }
}
?>