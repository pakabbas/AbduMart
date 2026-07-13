</main>
<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="text-danger">Abdu Market</h5>
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
                    <li><a href="index.php">Shop Products</a></li>
                    <li><a href="cart.php">View Cart</a></li>
                    <li><a href="orders.php">Track Orders</a></li>
                </ul>
            </div>
        </div>
        <hr class="my-4">
        <p class="text-center text-muted small mb-0">&copy; <?= date('Y') ?> Abdu Market. All rights reserved.</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
