<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

use App\CloverCheckoutService;
use App\StripeService;

$sessionId = trim((string) ($_GET['session_id'] ?? $_GET['checkoutSessionId'] ?? $_GET['checkout_session_id'] ?? ''));
$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
$order = null;

if ($sessionId !== '') {
    try {
        $looksLikeStripe = str_starts_with($sessionId, 'cs_');
        if ($provider === 'clover' || ($provider === '' && !$looksLikeStripe)) {
            $order = (new CloverCheckoutService())->fulfillSession($sessionId);
        }
        if (!$order && ($provider === 'stripe' || $looksLikeStripe || $provider === '')) {
            $order = (new StripeService())->fulfillSession($sessionId);
        }
    } catch (Throwable $e) {
        flash('danger', 'Could not verify payment: ' . $e->getMessage());
        redirect('orders.php');
    }
} elseif ($provider === 'clover') {
    // Dashboard redirect may omit session id; use the shopper's latest pending/paid Clover order.
    $userId = (int) current_user()['id'];
    $stmt = db()->prepare(
        "SELECT * FROM orders
         WHERE user_id = ? AND payment_method = 'clover'
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $latest = $stmt->fetch() ?: null;
    if ($latest && in_array(($latest['status'] ?? ''), ['paid', 'preparing', 'ready'], true)) {
        $order = $latest;
    } elseif ($latest && ($latest['status'] ?? '') === 'pending' && !empty($latest['clover_checkout_session_id'])) {
        try {
            $order = (new CloverCheckoutService())->fulfillSession((string) $latest['clover_checkout_session_id']);
        } catch (Throwable) {
            $order = null;
        }
    }
}

if (!$order) {
    if ($sessionId === '') {
        flash('warning', 'Payment verification pending. Check your orders shortly.');
        redirect('orders.php');
    }
    $pageTitle = 'Verifying Payment';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div id="verify-screen">
                    <div class="mb-4">
                        <div class="spinner-border text-danger" role="status" style="width:3rem;height:3rem">
                            <span class="visually-hidden">Verifying…</span>
                        </div>
                    </div>
                    <h1 class="h4 mb-2">Verifying your payment</h1>
                    <p class="text-muted mb-0">Please wait while we confirm your payment. This usually takes a few seconds.</p>
                </div>
                <div id="verify-fail" style="display:none">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:3rem"></i>
                    </div>
                    <h1 class="h4 mb-2">Payment could not be verified</h1>
                    <p class="text-muted mb-3" id="verify-fail-msg">Something went wrong. Please check your orders or try again.</p>
                    <a href="orders.php" class="btn btn-outline-danger">View my orders</a>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var pollUrl = <?= json_encode('api/payment-status.php?session_id=' . urlencode($sessionId) . '&provider=' . urlencode($provider)) ?>;
        var maxAttempts = 20;
        var interval = 2000;
        var attempt = 0;

        function poll() {
            attempt++;
            fetch(pollUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'paid') {
                        window.location.href = 'order-success.php?session_id=' + <?= json_encode(urlencode($sessionId)) ?> + '&provider=' + <?= json_encode(urlencode($provider)) ?>;
                        return;
                    }
                    if (data.status === 'error' || data.status === 'failed') {
                        showFail(data.message || 'Payment was not completed.');
                        return;
                    }
                    if (attempt >= maxAttempts) {
                        showFail('Verification timed out. Your payment may still be processing — please check your orders.');
                        return;
                    }
                    setTimeout(poll, interval);
                })
                .catch(function () {
                    if (attempt >= maxAttempts) {
                        showFail('Could not reach the server. Please check your orders.');
                        return;
                    }
                    setTimeout(poll, interval);
                });
        }

        function showFail(msg) {
            document.getElementById('verify-screen').style.display = 'none';
            var fail = document.getElementById('verify-fail');
            fail.style.display = '';
            if (msg) {
                document.getElementById('verify-fail-msg').textContent = msg;
            }
        }

        poll();
    })();
    </script>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
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
