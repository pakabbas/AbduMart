<?php

declare(strict_types=1);

$adminSection = $adminSection ?? '';
?>
<div class="admin-subnav mb-4">
    <div class="d-flex flex-wrap gap-2">
        <a href="index.php" class="btn btn-sm <?= $adminSection === 'dashboard' ? 'btn-danger' : 'btn-outline-danger' ?>">Dashboard</a>
        <a href="orders.php" class="btn btn-sm <?= $adminSection === 'orders' ? 'btn-danger' : 'btn-outline-danger' ?>">Orders</a>
        <a href="settings.php" class="btn btn-sm <?= $adminSection === 'settings' ? 'btn-danger' : 'btn-outline-danger' ?>">Settings</a>
    </div>
</div>
