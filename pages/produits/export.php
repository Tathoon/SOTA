<?php
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin(['Admin', 'Gérant']);

$manager = new SotaManager();
$user = getCurrentUser();

// Récupération des filtres
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$statut_stock = $_GET['statut_stock'] ?? '';
$format = $_GET['format'] ?? 'csv';

try {
    // Récupération des produits avec les mêmes filtres
    $produits = $manager->getProduits($search, $category, $statut_stock);
    
    if ($format === 'csv') {
        // Génération du fichier CSV
        $filename = 'produits_sota_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csvContent ?? ''));
        
        // Ouverture du flux de sortie
        $output = fopen('php://output', 'w');
        
        // BOM UTF-8 pour Excel
        fputs($output, "\xEF\xBB\xBF");
        
        // Headers CSV
        fputcsv($output, [
            'Référence',
            'Nom',
            'Description',
            'Catégorie',
            'Marque',
            'Collection',
            'Taille',
            'Couleur',
            'Composition',
            'Saison',
            'Stock actuel',
            'Seuil minimum',
            'Prix achat (€)',
            'Prix vente (€)',
            'Marge (€)',
            'Marge (%)',
            'Statut stock',
            'Emplacement',
            'Lot minimum',
            'Poids (g)',
            'Date création',
            'Dernière modification'
        ], ';');
        
        // Données
        foreach ($produits as $produit) {
            $marge_euro = 0;
            $marge_pct = 0;
            
            if ($produit['prix_achat'] && $produit['prix_achat'] > 0) {
                $marge_euro = $produit['prix_vente'] - $produit['prix_achat'];
                $marge_pct = ($marge_euro / $produit['prix_achat']) * 100;
            }
            
            fputcsv($output, [
                $produit['reference'],
                $produit['nom'],
                $produit['description'],
                $produit['categorie_nom'] ?? 'Non classé',
                $produit['marque'],
                $produit['collection'],
                $produit['taille'],
                $produit['couleur'],
                $produit['composition'],
                $produit['saison'],
                $produit['stock_actuel'],
                $produit['seuil_minimum'],
                $produit['prix_achat'] ? number_format($produit['prix_achat'], 2, ',', '') : '',
                number_format($produit['prix_vente'], 2, ',', ''),
                $marge_euro ? number_format($marge_euro, 2, ',', '') : '',
                $marge_pct ? number_format($marge_pct, 1, ',', '') : '',
                ucfirst($produit['statut_stock']),
                $produit['emplacement'],
                $produit['lot_minimum'],
                $produit['poids'] ? number_format($produit['poids'], 1, ',', '') : '',
                date('d/m/Y H:i', strtotime($produit['created_at'])),
                date('d/m/Y H:i', strtotime($produit['updated_at']))
            ], ';');
        }
        
        fclose($output);
        
        // Log de l'activité
        logActivite('export_produits', [
            'format' => $format,
            'nb_produits' => count($produits),
            'filtres' => compact('search', 'category', 'statut_stock')
        ], $user['id']);
        
        exit();
        
    } elseif ($format === 'excel') {
        // Pour Excel, on peut utiliser une librairie comme PhpSpreadsheet
        // Pour l'instant, on redirige vers CSV
        header('Location: export.php?format=csv&' . http_build_query($_GET));
        exit();
        
    } else {
        throw new Exception("Format d'export non supporté");
    }
    
} catch (Exception $e) {
    // En cas d'erreur, rediriger vers la liste avec un message d'erreur
    header('Location: produits.php?error=' . urlencode('Erreur lors de l\'export : ' . $e->getMessage()));
    exit();
}
?>