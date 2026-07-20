<?php
/** @var string $pageTitle */
/** @var string|null $pageDescription */
$pageTitle = $pageTitle ?? config('app.name');
$pageDescription = $pageDescription ?? 'Shop Abdu Market online for curbside pickup in Canton, Michigan.';
$headerUserId = is_logged_in() ? (int) current_user()['id'] : null;
$headerCart = get_cart_totals($headerUserId);
$cartCount = (int) $headerCart['count'];
$cartSubtotalLabel = format_money($headerCart['subtotal']);
$headerSearch = trim($_GET['q'] ?? '');
$headerCategories = get_categories();
$martAddress = setting('mart.address', config('mart.address'));
$martPhone = setting('mart.phone', config('mart.phone'));
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isHome = $currentScript === 'index.php';
$isShop = in_array($currentScript, ['shop.php', 'product.php'], true);
$isAbout = $currentScript === 'about.php';
$isContact = $currentScript === 'contact.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Abdu Market</title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="icon" type="image/png" href="<?= e(asset_url('assets/images/abdu-market-logo.png')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="pickup-here-url" content="<?= e(asset_url('pickup-here.php')) ?>">
    <meta name="mart-line-url" content="<?= e(asset_url('mart-line.php')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Libre+Baskerville:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset_url('assets/css/style.css')) ?>" rel="stylesheet">
    <style><?= theme_inline_css() ?></style>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<header class="site-header sticky-top" id="siteHeader">
    <div class="site-header-top">
        <div class="container site-header-top-inner">
            <p class="site-header-location mb-0">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                <span>Store Location: <?= e($martAddress) ?></span>
            </p>
            <div class="site-header-top-meta">
                <span class="site-header-meta-item" title="Language">Eng <i class="bi bi-caret-down-fill" aria-hidden="true"></i></span>
                <span class="site-header-meta-item" title="Currency">USD <i class="bi bi-caret-down-fill" aria-hidden="true"></i></span>
                <?php if (is_logged_in()): ?>
                    <a class="site-header-meta-link" href="orders.php">My Orders</a>
                    <?php if (is_admin()): ?>
                    <a class="site-header-meta-link" href="admin/">Dashboard</a>
                    <?php endif; ?>
                    <a class="site-header-meta-link" href="logout.php">Sign Out</a>
                <?php else: ?>
                    <a class="site-header-meta-link" href="login.php">Sign In</a>
                    <a class="site-header-meta-link" href="register.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="site-header-main">
        <div class="container site-header-main-inner">
            <a class="site-header-brand" href="index.php" aria-label="Abdu Market home">
                <img
                    src="<?= e(asset_url('assets/images/abdu-market-logo.png')) ?>"
                    alt="Abdu Market"
                    class="site-header-logo"
                    width="240"
                    height="40"
                >
                <span class="site-header-wordmark">Abdu Market</span>
            </a>

            <form class="site-header-search" action="shop.php" method="get" role="search">
                <label class="visually-hidden" for="siteHeaderSearch">Search products</label>
                <i class="bi bi-search site-header-search-icon" aria-hidden="true"></i>
                <input
                    id="siteHeaderSearch"
                    type="search"
                    name="q"
                    class="site-header-search-input"
                    placeholder="Search"
                    value="<?= e($headerSearch) ?>"
                    autocomplete="off"
                >
                <button type="submit" class="site-header-search-btn">Search</button>
            </form>

            <div class="site-header-actions">
                <a href="<?= is_logged_in() ? 'orders.php' : 'login.php' ?>" class="site-header-wishlist" aria-label="<?= is_logged_in() ? 'My orders' : 'Sign in' ?>">
                    <i class="bi bi-heart" aria-hidden="true"></i>
                </a>
                <span class="site-header-actions-divider" aria-hidden="true"></span>
                <a href="#" class="site-header-cart js-floating-cart-open" role="button" aria-label="Open shopping cart">
                    <span class="site-header-cart-icon-wrap">
                        <i class="bi bi-bag" aria-hidden="true"></i>
                        <span class="cart-badge<?= $cartCount > 0 ? '' : ' is-empty' ?>"<?= $cartCount > 0 ? '' : ' style="display:none"' ?>><?= (int) $cartCount ?></span>
                    </span>
                    <span class="site-header-cart-copy">
                        <span class="site-header-cart-label">Shopping cart:</span>
                        <strong class="site-header-cart-total js-header-cart-total"><?= e($cartSubtotalLabel) ?></strong>
                    </span>
                </a>
                <button class="site-header-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteHeaderNav" aria-controls="siteHeaderNav" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="site-header-nav-bar">
        <div class="container">
            <div class="collapse navbar-collapse site-header-nav-collapse" id="siteHeaderNav">
                <nav class="site-header-nav" aria-label="Primary">
                    <ul class="site-header-menu">
                        <li>
                            <a href="index.php" class="site-header-menu-link<?= $isHome ? ' is-active' : '' ?>">Home</a>
                        </li>
                        <li class="dropdown site-header-shop-item">
                            <a href="shop.php" class="site-header-menu-link<?= $isShop ? ' is-active' : '' ?>">Shop</a>
                            <button
                                type="button"
                                class="site-header-shop-caret dropdown-toggle dropdown-toggle-split"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                aria-label="Shop categories"
                            ></button>
                            <ul class="dropdown-menu site-header-shop-menu">
                                <li><a class="dropdown-item" href="shop.php">All products</a></li>
                                <?php if ($headerCategories): ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php foreach ($headerCategories as $cat): ?>
                                <li>
                                    <a class="dropdown-item" href="shop.php?category=<?= (int) $cat['id'] ?>">
                                        <?= e($cat['name']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li>
                            <a href="about.php" class="site-header-menu-link<?= $isAbout ? ' is-active' : '' ?>">About Us</a>
                        </li>
                        <li>
                            <a href="contact.php" class="site-header-menu-link<?= $isContact ? ' is-active' : '' ?>">Contact Us</a>
                        </li>
                    </ul>
                    <a class="site-header-phone" href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $martPhone)) ?>">
                        <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                        <span><?= e($martPhone) ?></span>
                    </a>
                </nav>
            </div>
        </div>
    </div>
</header>
<main class="page-main">
    <?php foreach (get_flashes() as $flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endforeach; ?>
