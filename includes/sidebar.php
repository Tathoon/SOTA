<?php
$user = getCurrentUser();
$role = $user['role'] ?? 'Aucun';

function active($path) {
    $current_path = $_SERVER['REQUEST_URI'];
    
    // Gestion spéciale pour éviter les multiples actifs
    if ($path === 'commandes_fournisseurs') {
        return strpos($current_path, 'commandes_fournisseurs') !== false ? 'active' : '';
    }
    if ($path === 'commandes' && strpos($current_path, 'commandes_fournisseurs') !== false) {
        return ''; // Ne pas activer "commandes" si on est sur "commandes_fournisseurs"
    }
    
    return strpos($current_path, $path) !== false ? 'active' : '';
}
?>

<div class="sidebar">
    <div class="menu-header">
        <i class="fas fa-tshirt"></i> SOTA Fashion
    </div>

    <ul class="menu-list">
        <!-- Dashboard - Accessible à tous -->
        <li class="<?= active('dashboard') ?>">
            <a href="/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        </li>

        <!-- GESTION PRODUITS - Admin, Gérant -->
        <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
            <li class="<?= active('produits') ?>">
                <a href="/pages/produits/produits.php"><i class="fas fa-boxes"></i> Lots</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/produits/nouveau.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-plus"></i> Nouveau lot</a>
            </li>
            
            <li class="<?= active('categories') ?>">
                <a href="/pages/categories/categories.php"><i class="fas fa-tags"></i> Catégories</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/categories/nouvelle.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-plus"></i> Nouvelle catégorie</a>
            </li>
        <?php endif; ?>

        <!-- CONSULTATION PRODUITS - Commercial, Préparateur (lecture seule) -->
        <?php if (in_array($role, ['Commercial', 'Préparateur'])): ?>
            <li class="<?= active('produits') ?>">
                <a href="/pages/produits/produits.php"><i class="fas fa-boxes"></i> Lots <small style="color: #888;">(consultation)</small></a>
            </li>
        <?php endif; ?>

        <!-- GESTION FOURNISSEURS - Admin, Gérant uniquement -->
        <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
            <li class="<?= active('fournisseurs') ?>">
                <a href="/pages/fournisseurs/fournisseurs.php"><i class="fas fa-truck-loading"></i> Fournisseurs</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/fournisseurs/nouveau.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-plus"></i> Nouveau fournisseur</a>
            </li>
        <?php endif; ?>

        <!-- GESTION STOCKS - Admin, Préparateur -->
        <?php if (in_array($role, ['Admin', 'Préparateur'])): ?>
            <li class="<?= active('stocks') ?>">
                <a href="/pages/stocks/stocks.php"><i class="fas fa-warehouse"></i> Stocks</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/stocks/mouvement.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-exchange-alt"></i> Mouvement stock</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/stocks/historique.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-history"></i> Historique</a>
            </li>
        <?php endif; ?>

        <!-- CONSULTATION STOCKS - Gérant, Commercial -->
        <?php if (in_array($role, ['Gérant', 'Commercial'])): ?>
            <li class="<?= active('stocks') ?>">
                <a href="/pages/stocks/stocks.php"><i class="fas fa-warehouse"></i> Stocks <small style="color: #888;">(consultation)</small></a>
            </li>
        <?php endif; ?>

        <!-- GESTION COMMANDES - Admin, Commercial -->
        <?php if (in_array($role, ['Admin', 'Commercial'])): ?>
            <li class="<?= active('commandes') ?>">
                <a href="/pages/commandes/commandes.php"><i class="fas fa-shopping-cart"></i> Commandes</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/commandes/nouvelle.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-plus"></i> Nouvelle commande</a>
            </li>
        <?php endif; ?>

        <!-- CONSULTATION COMMANDES - Gérant, Préparateur, Livreur -->
        <?php if (in_array($role, ['Gérant', 'Préparateur', 'Livreur'])): ?>
            <li class="<?= active('commandes') ?>">
                <a href="/pages/commandes/commandes.php"><i class="fas fa-shopping-cart"></i> Commandes 
                    <small style="color: #888;">
                        <?php 
                        if ($role === 'Préparateur') echo '(préparation)';
                        elseif ($role === 'Livreur') echo '(livraison)';
                        else echo '(consultation)';
                        ?>
                    </small>
                </a>
            </li>
        <?php endif; ?>

        <!-- GESTION LIVRAISONS - Admin, Livreur -->
        <?php if (in_array($role, ['Admin', 'Livreur'])): ?>
            <li class="<?= active('livraisons') ?>">
                <a href="/pages/livraisons/livraisons.php"><i class="fas fa-truck"></i> Livraisons</a>
            </li>
            <li style="margin-left: 20px;">
                <a href="/pages/livraisons/nouvelle.php" style="font-size: 13px; color: #bbb;"><i class="fas fa-plus"></i> Nouvelle livraison</a>
            </li>
        <?php endif; ?>

        <!-- CONSULTATION LIVRAISONS - Gérant, Commercial, Préparateur -->
        <?php if (in_array($role, ['Gérant', 'Commercial', 'Préparateur'])): ?>
            <li class="<?= active('livraisons') ?>">
                <a href="/pages/livraisons/livraisons.php"><i class="fas fa-truck"></i> Livraisons <small style="color: #888;">(consultation)</small></a>
            </li>
        <?php endif; ?>

        <!-- COMMANDES FOURNISSEURS - Admin, Gérant -->
        <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
            <li class="<?= active('commandes_fournisseurs') ?>">
                <a href="/pages/fournisseurs/commandes_fournisseurs.php"><i class="fas fa-truck-loading"></i> Commandes fournisseurs</a>
            </li>
        <?php endif; ?>

        <!-- GESTION UTILISATEURS - Admin uniquement -->
        <?php if ($role === 'Admin'): ?>
            <li class="<?= active('utilisateurs') ?>">
                <a href="/pages/utilisateurs/utilisateurs.php"><i class="fas fa-users"></i> Utilisateurs</a>
            </li>
        <?php endif; ?>

        <!-- RAPPORTS - Admin, Gérant -->
        <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
            <li class="<?= active('rapports') ?>">
                <a href="/pages/rapports/rapports.php"><i class="fas fa-chart-pie"></i> Rapports</a>
            </li>
        <?php endif; ?>

        <!-- RAPPORTS LIMITÉS - Commercial (ventes), Préparateur (stocks) -->
        <?php if ($role === 'Commercial'): ?>
            <li class="<?= active('rapports') ?>">
                <a href="/pages/rapports/rapports.php"><i class="fas fa-chart-line"></i> Rapports ventes</a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'Préparateur'): ?>
            <li class="<?= active('rapports') ?>">
                <a href="/pages/rapports/rapports.php"><i class="fas fa-chart-bar"></i> Rapports stocks</a>
            </li>
        <?php endif; ?>

        <!-- INTÉGRATION SAGE - Admin uniquement -->
        <?php if ($role === 'Admin'): ?>
            <li class="<?= active('sage') ?>">
                <a href="/api/sage_integration.php"><i class="fas fa-sync"></i> Sync SAGE</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="user-section">
        <i class="fas fa-user-circle"></i>
        <div>
            <div><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
            <div style="font-size: 0.8em; opacity: 0.8;">
                <?= ucfirst($role) ?>
                <?php if ($role !== 'Admin'): ?>
                    <i class="fas fa-info-circle" title="Accès limité selon votre rôle" style="margin-left: 5px; opacity: 0.6;"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>