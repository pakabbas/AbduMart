<?php
/** @var string $pageTitle */
/** @var string $adminSection */
/** @var string|null $pageSubtitle */
/** @var string|null $headerActions */
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? null;
$headerActions = $headerActions ?? '';
$adminUser = current_user();
$adminInitials = strtoupper(substr($adminUser['first_name'] ?? 'A', 0, 1) . substr($adminUser['last_name'] ?? 'M', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Abdu Market Admin</title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-app">
<div class="admin-overlay" id="adminOverlay"></div>
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <span class="admin-logo">AM</span>
            <div>
                <strong>Abdu Market</strong>
                <span>Admin Console</span>
            </div>
        </div>

        <nav class="admin-sidebar-nav">
            <span class="admin-nav-group">Operations</span>
            <a href="index.php" class="admin-sidebar-link <?= ($adminSection ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="orders.php" class="admin-sidebar-link <?= ($adminSection ?? '') === 'orders' ? 'active' : '' ?>">
                <i class="bi bi-bag-check"></i> Orders
            </a>
            <span class="admin-nav-group">Configuration</span>
            <a href="settings.php" class="admin-sidebar-link <?= ($adminSection ?? '') === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i> Settings
            </a>
        </nav>

        <div class="admin-sidebar-footer">
            <a href="../index.php" class="admin-sidebar-link muted" target="_blank">
                <i class="bi bi-shop"></i> View storefront
            </a>
            <div class="admin-user-pill">
                <span class="admin-user-avatar"><?= e($adminInitials) ?></span>
                <div class="admin-user-meta">
                    <strong><?= e($adminUser['first_name'] ?? 'Admin') ?></strong>
                    <span><?= e($adminUser['email'] ?? '') ?></span>
                </div>
            </div>
            <a href="../logout.php" class="admin-sidebar-link muted">
                <i class="bi bi-box-arrow-right"></i> Sign out
            </a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-top-header">
            <button type="button" class="admin-menu-btn" id="adminMenuBtn" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="admin-top-header-text">
                <h1><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle): ?>
                <p><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($headerActions): ?>
            <div class="admin-header-actions"><?= $headerActions ?></div>
            <?php endif; ?>
        </header>

        <main class="admin-page">
            <?php foreach (get_flashes() as $flash): ?>
            <div class="admin-toast admin-toast-<?= e($flash['type']) ?>">
                <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                <?= e($flash['message']) ?>
            </div>
            <?php endforeach; ?>
