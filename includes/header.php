<?php
/** @var string $pageTitle */
/** @var string|null $pageDescription */
$pageTitle = $pageTitle ?? config('app.name');
$pageDescription = $pageDescription ?? 'Shop Abdu Market online for curbside pickup in Canton, Michigan.';
$cartCount = is_logged_in() ? get_cart_count((int) current_user()['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Abdu Market</title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="pickup-here-url" content="<?= e(asset_url('pickup-here.php')) ?>">
    <meta name="mart-line-url" content="<?= e(asset_url('mart-line.php')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset_url('assets/css/style.css')) ?>" rel="stylesheet">
    <style><?= theme_inline_css() ?></style>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <span class="brand-mark">AM</span>
            <span class="brand-text">
                <strong>Abdu Market</strong>
                <small>Curbside Pickup · Canton, MI</small>
            </span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                <?php if (is_logged_in()): ?>
                <li class="nav-item"><a class="nav-link" href="orders.php">My Orders</a></li>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                <li class="nav-item"><a class="nav-link" href="admin/">Mart Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="#" class="btn btn-outline-danger position-relative js-floating-cart-open" role="button">
                    <i class="bi bi-bag"></i>
                    <span class="d-none d-sm-inline ms-1">Cart</span>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <button class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown">
                            <?= e(current_user()['first_name']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Sign Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-link text-dark text-decoration-none">Sign In</a>
                    <a href="register.php" class="btn btn-danger">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="page-main">
    <?php foreach (get_flashes() as $flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endforeach; ?>
