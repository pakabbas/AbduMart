<?php
/** @var int $cartCount */
$cartCount = $cartCount ?? 0;
$storeStatus = store_status();
$storeClosed = !$storeStatus['open'];
?>
<div id="floatingCart" class="floating-cart" data-count="<?= (int) $cartCount ?>" data-store-closed="<?= $storeClosed ? '1' : '0' ?>" data-basket-url="<?= e(asset_url('shop-items.php')) ?>" data-shop-url="<?= e(asset_url('mart-line.php')) ?>">
    <button type="button" class="floating-cart-fab" id="floatingCartFab" aria-label="Open cart">
        <i class="bi bi-bag-fill"></i>
        <span class="floating-cart-fab-badge" id="floatingCartFabBadge" <?= $cartCount > 0 ? '' : 'hidden' ?>><?= (int) $cartCount ?></span>
    </button>

    <div class="floating-cart-overlay" id="floatingCartOverlay" hidden></div>

    <aside class="floating-cart-panel" id="floatingCartPanel" aria-label="Shopping cart">
        <div class="floating-cart-panel-header">
            <h2><i class="bi bi-bag me-2"></i>Your Cart</h2>
            <button type="button" class="floating-cart-close" id="floatingCartClose" aria-label="Close cart">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="floating-cart-panel-body" id="floatingCartBody">
            <div class="floating-cart-loading text-center py-5 text-muted">
                <span class="spinner-border spinner-border-sm"></span> Loading…
            </div>
        </div>
        <div class="floating-cart-panel-footer" id="floatingCartFooter" hidden>
            <div class="floating-cart-totals">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Subtotal</span>
                    <span id="floatingCartSubtotal">$0.00</span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>Tax</span>
                    <span id="floatingCartTax">$0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                    <span>Total</span>
                    <span class="text-danger" id="floatingCartTotal">$0.00</span>
                </div>
            </div>
            <a href="<?= e(is_logged_in() ? asset_url('checkout.php') : asset_url('login.php?redirect=' . rawurlencode('checkout.php'))) ?>" class="btn btn-danger w-100 btn-lg" id="floatingCartCheckoutBtn"><?= is_logged_in() ? 'Checkout' : 'Sign in to checkout' ?></a>
            <a href="<?= e(asset_url('cart.php')) ?>" class="btn btn-link w-100 mt-2 small">View full cart</a>
        </div>
        <div class="floating-cart-panel-footer" id="floatingCartFooterClosed" hidden>
            <div class="floating-cart-totals">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Subtotal</span>
                    <span id="floatingCartSubtotalClosed">$0.00</span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>Tax</span>
                    <span id="floatingCartTaxClosed">$0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                    <span>Total</span>
                    <span class="text-danger" id="floatingCartTotalClosed">$0.00</span>
                </div>
            </div>
            <div class="alert alert-warning small py-2 mb-2"><?= e($storeStatus['banner_message']) ?></div>
            <button type="button" class="btn btn-secondary w-100 btn-lg" disabled>Checkout unavailable</button>
            <a href="<?= e(asset_url('cart.php')) ?>" class="btn btn-link w-100 mt-2 small">View full cart</a>
        </div>
    </aside>
</div>
