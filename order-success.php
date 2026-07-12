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

$pageTitle = 'Order Confirmed';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 text-center">
            <div class="success-icon mb-4">
                <i class="bi bi-check-circle-fill text-danger"></i>
            </div>
            <h1 class="section-title">Thank you for your order!</h1>
            <p class="lead text-muted">Order <strong><?= e($order['order_number']) ?></strong> is confirmed and being prepared.</p>
            <div class="card border-0 shadow-sm text-start mt-4">
                <div class="card-body p-4">
                    <h2 class="h5">What's next?</h2>
                    <ol class="mb-0">
                        <li>Head to Abdu Mart at <?= e(setting('mart.address', config('mart.address'))) ?></li>
                        <li>When you arrive, open your order and tap <strong>I'm Here</strong></li>
                        <li>We'll bring your groceries to your car</li>
                    </ol>
                </div>
            </div>
            <a href="orders.php" class="btn btn-danger btn-lg mt-4">View My Orders</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
