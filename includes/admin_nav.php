<?php

declare(strict_types=1);

$adminSection = $adminSection ?? '';
?>
<div class="admin-topbar">
    <div class="container-fluid">
        <div class="admin-topbar-inner">
            <div class="admin-topbar-brand">
                <span class="brand-mark">AM</span>
                <div>
                    <strong>Abdu Market</strong>
                    <small>Admin Panel</small>
                </div>
            </div>
            <nav class="admin-topbar-nav">
                <a href="index.php" class="admin-nav-link <?= $adminSection === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="orders.php" class="admin-nav-link <?= $adminSection === 'orders' ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i> Orders
                </a>
                <a href="settings.php" class="admin-nav-link <?= $adminSection === 'settings' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </nav>
            <a href="../index.php" class="btn btn-sm btn-outline-danger admin-store-link" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> View Store
            </a>
        </div>
    </div>
</div>
