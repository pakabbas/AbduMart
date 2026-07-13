<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = (int) current_user()['id'];
$cart = get_cart_totals($userId);

$pageTitle = 'Your Cart';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <h1 class="section-title mb-4">Your Cart</h1>

    <?php if (empty($cart['items'])): ?>
    <div class="empty-state text-center py-5">
        <i class="bi bi-bag display-4 text-danger"></i>
        <p class="mt-3 text-muted">Your cart is empty.</p>
        <a href="index.php" class="btn btn-danger">Browse Products</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="list-group list-group-flush">
                    <?php foreach ($cart['items'] as $item): ?>
                    <div class="list-group-item py-3 cart-item">
                        <div class="row align-items-center g-3">
                            <div class="col-auto">
                                <div class="cart-thumb catalog-tile-media<?= catalog_has_image($item['image_url'] ?? null) ? '' : ' show-initials' ?>">
                                    <?= catalog_tile_media($item['name'], $item['image_url'] ?? null) ?>
                                </div>
                            </div>
                            <div class="col">
                                <h3 class="h6 mb-1"><?= e($item['name']) ?></h3>
                                <span class="text-muted"><?= format_money($item['price']) ?> each</span>
                            </div>
                            <div class="col-auto">
                                <form method="post" action="<?= e(asset_url('cart-action.php')) ?>" class="d-flex align-items-center gap-2 qty-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                    <button type="button" class="btn btn-sm btn-outline-secondary qty-minus">−</button>
                                    <input type="number" name="quantity" class="form-control form-control-sm qty-input text-center" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['inventory'] ?>">
                                    <button type="button" class="btn btn-sm btn-outline-secondary qty-plus">+</button>
                                </form>
                            </div>
                            <div class="col-auto fw-semibold">
                                <?= format_money((float) $item['price'] * (int) $item['quantity']) ?>
                            </div>
                            <div class="col-auto">
                                <form method="post" action="<?= e(asset_url('cart-action.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm summary-card sticky-lg-top">
                <div class="card-body">
                    <h2 class="h5 mb-3">Order Summary</h2>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?= format_money($cart['subtotal']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>Tax (6%)</span>
                        <span><?= format_money($cart['tax']) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                        <span>Total</span>
                        <span class="text-danger"><?= format_money($cart['total']) ?></span>
                    </div>
                    <a href="checkout.php" class="btn btn-danger w-100 btn-lg">Proceed to Checkout</a>
                    <p class="small text-muted mt-3 mb-0 text-center">Curbside pickup only · Pay with Stripe</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
