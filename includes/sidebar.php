<?php
$user = getCurrentUser();
$role = $user['role'] ?? 'Aucun';

function active($path) {
    return strpos($_SERVER['REQUEST_URI'], $path) !== false ? 'active' : '';
}
?>

<div class="sidebar">
    <div class="menu-header">
        <i class="fas fa-tshirt"></i> SOTA
    </div>

    <ul class="menu-list">
        <li class="<?= active('/dashboard') ?>">
            <a href="/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        </li>

        <?php if (in_array($role, ['Admin', 'Gérant'])): ?>
            <li class="<?= active('/produits') ?>">
                <a href="/pages/produits/produits.php"><i class="fas fa-box"></i> Produits</a>
            </li>
            <li class="<?= active('/categories') ?>">
                <a href="/pages/categories/categories.php"><i class="fas fa-tags"></i> Catégories</a>
            </li>
            <li class="<?= active('/fournisseurs') ?>">
                <a href="/pages/fournisseurs/fournisseurs.php"><i class="fas fa-truck-loading"></i> Fournisseurs</a>
            </li>
        <?php endif; ?>

        <?php if (in_array($role, ['Admin', 'Préparateur'])): ?>
            <li class="<?= active('/stocks') ?>">
                <a href="/pages/stocks/stocks.php"><i class="fas fa-warehouse"></i> Stocks</a>
            </li>
        <?php endif; ?>

        <?php if (in_array($role, ['Admin', 'Commercial'])): ?>
            <li class="<?= active('/commandes') ?>">
                <a href="/pages/commandes/commandes.php"><i class="fas fa-shopping-cart"></i> Commandes</a>
            </li>
        <?php endif; ?>

        <?php if (in_array($role, ['Admin', 'Livreur'])): ?>
            <li class="<?= active('/livraisons') ?>">
                <a href="/pages/livraisons/livraisons.php"><i class="fas fa-truck"></i> Livraisons</a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'Admin'): ?>
            <li class="<?= active('/utilisateurs') ?>">
                <a href="/pages/utilisateurs/"><i class="fas fa-users"></i> Utilisateurs</a>
            </li>
            <li class="<?= active('/profils') ?>">
                <a href="/pages/profils/"><i class="fas fa-user-shield"></i> Profils</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="user-section">
        <i class="fas fa-user-circle"></i>
        <div>
            <div><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
            <div style="font-size: 0.8em;"><?= ucfirst($role) ?></div>
        </div>
    </div>
</div>
