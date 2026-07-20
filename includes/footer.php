</main>
<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-md-4">
                <img
                    src="<?= e(asset_url('assets/images/abdu-market-logo.png')) ?>"
                    alt="Abdu Market"
                    class="footer-brand-logo mb-2"
                    width="220"
                    height="36"
                >
                <p class="text-muted mb-1">Your neighborhood market in Canton, Michigan.</p>
                <p class="text-muted small mb-0">Order online, pay securely, and pick up curbside.</p>
            </div>
            <div class="col-md-4">
                <h6>Pickup Location</h6>
                <p class="text-muted small mb-0"><?= e(setting('mart.address', config('mart.address'))) ?></p>
                <p class="text-muted small"><?= e(setting('mart.phone', config('mart.phone'))) ?></p>
            </div>
            <div class="col-md-4">
                <h6>Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="shop.php">Shop Products</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="orders.php">Track Orders</a></li>
                </ul>
            </div>
        </div>
        <hr class="my-4">
        <p class="text-center text-muted small mb-0">&copy; <?= date('Y') ?> Abdu Market. All rights reserved.</p>
    </div>
</footer>
<?php
$storeStatus = store_status();
if (!$storeStatus['open']) {
    require __DIR__ . '/store_closed_banner.php';
}
$activePickupOrder = null;
if (is_logged_in()) {
    $activePickupOrder = get_active_pickup_order((int) current_user()['id']);
}
if ($activePickupOrder) {
    require __DIR__ . '/active_order_banner.php';
}
?>
<?php require __DIR__ . '/mobile_bottom_nav.php'; ?>
<?php require __DIR__ . '/floating_cart.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e(asset_url('assets/js/floating-cart.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/floating-cart.js') ?>"></script>
<script src="<?= e(asset_url('assets/js/app.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/app.js') ?>"></script>
</body>
</html>
