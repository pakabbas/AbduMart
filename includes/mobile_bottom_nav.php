<?php

declare(strict_types=1);

$bottomNavScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$bottomNavIsHome = $bottomNavScript === 'index.php';
$bottomNavIsShop = in_array($bottomNavScript, ['shop.php', 'product.php', 'cart.php', 'checkout.php'], true);
$bottomNavIsOrders = in_array($bottomNavScript, ['orders.php', 'order-success.php', 'pickup-here.php'], true);
$bottomNavCartCount = isset($cartCount) ? (int) $cartCount : 0;
$bottomNavOrdersHref = is_logged_in()
    ? asset_url('orders.php')
    : asset_url('login.php?redirect=' . rawurlencode('orders.php'));
?>
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile primary">
    <a href="<?= e(asset_url('index.php')) ?>" class="mobile-bottom-nav-item<?= $bottomNavIsHome ? ' is-active' : '' ?>">
        <i class="bi bi-house-door<?= $bottomNavIsHome ? '-fill' : '' ?>" aria-hidden="true"></i>
        <span>Home</span>
    </a>
    <a href="<?= e(asset_url('shop.php')) ?>" class="mobile-bottom-nav-item<?= $bottomNavIsShop ? ' is-active' : '' ?>">
        <i class="bi bi-grid<?= $bottomNavIsShop ? '-fill' : '' ?>" aria-hidden="true"></i>
        <span>Shop</span>
    </a>
    <a href="#" class="mobile-bottom-nav-item js-floating-cart-open" role="button" aria-label="Open shopping cart">
        <span class="mobile-bottom-nav-cart-wrap">
            <i class="bi bi-bag<?= $bottomNavCartCount > 0 ? '-fill' : '' ?>" aria-hidden="true"></i>
            <span class="cart-badge mobile-bottom-nav-badge<?= $bottomNavCartCount > 0 ? '' : ' is-empty' ?>"<?= $bottomNavCartCount > 0 ? '' : ' style="display:none"' ?>><?= $bottomNavCartCount ?></span>
        </span>
        <span>Cart</span>
    </a>
    <a href="<?= e($bottomNavOrdersHref) ?>" class="mobile-bottom-nav-item<?= $bottomNavIsOrders ? ' is-active' : '' ?>">
        <i class="bi bi-receipt" aria-hidden="true"></i>
        <span>Orders</span>
    </a>
</nav>
