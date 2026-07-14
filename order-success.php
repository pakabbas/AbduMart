<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

use App\StripeService;

$sessionId = $_GET['session_id'] ?? '';
$order = null;

if ($sessionId !== '') {
    try {
        $stripe = new StripeService();
        $order = $stripe->fulfillSession($sessionId);
    } catch (Throwable $e) {
        flash('danger', 'Could not verify payment: ' . $e->getMessage());
        redirect('orders.php');
    }
}

if (!$order) {
    flash('warning', 'Payment verification pending. Check your orders shortly.');
    redirect('orders.php');
}

$mapEmbed = config('mart.map_embed_url');
$pageTitle = 'Order Confirmed';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <div class="success-icon mb-3">
                    <i class="bi bi-check-circle-fill text-danger"></i>
                </div>
                <h1 class="section-title">Thank you for your order!</h1>
                <p class="lead text-muted mb-0">Order <strong><?= e($order['order_number']) ?></strong> is confirmed and being prepared.</p>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3"><i class="bi bi-signpost-2 text-danger me-2"></i>Pickup at Abdu Market</h2>
                    <p class="mb-3"><?= e(setting('mart.address', config('mart.address'))) ?></p>
                    <?php if ($mapEmbed): ?>
                    <div class="order-map-wrap ratio ratio-16x9 rounded-3 overflow-hidden">
                        <iframe src="<?= e($mapEmbed) ?>" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" title="Abdu Market location"></iframe>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">When you arrive</h2>
                    <ol class="mb-0">
                        <li class="mb-2">Drive to the curb-side pickup area at Abdu Market</li>
                        <li class="mb-2">Tap the big red <strong>I'm Here</strong> button below</li>
                        <li>We'll bring your order to your car</li>
                    </ol>
                </div>
            </div>

            <?php require __DIR__ . '/includes/im_here_panel.php'; ?>

            <?php $callMart = call_mart_button(false, 'btn btn-outline-danger'); ?>
            <?php if ($callMart !== ''): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-telephone text-danger me-1"></i> Call the store</h2>
                        <p class="text-muted mb-0 small">Questions about pickup? Reach Abdu Market at <?= e(mart_phone_number()) ?></p>
                    </div>
                    <?= $callMart ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                <a href="orders.php?order=<?= (int) $order['id'] ?>" class="btn btn-outline-danger">View order details</a>
                <a href="index.php" class="btn btn-danger">Continue shopping</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
