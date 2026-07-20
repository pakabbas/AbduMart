<?php

declare(strict_types=1);

/** @var array $activePickupOrder */
$status = order_status_display((string) $activePickupOrder['status']);
$orderUrl = 'orders.php?order=' . (int) $activePickupOrder['id'];
$checkedIn = !empty($activePickupOrder['customer_here_at']);
$callMart = call_mart_button(true, 'active-order-banner-call');
?>
<div class="active-order-banner" id="activeOrderBanner" data-order-id="<?= (int) $activePickupOrder['id'] ?>">
    <div class="active-order-banner-inner container">
        <a href="<?= e($orderUrl) ?>" class="active-order-banner-main" aria-label="View active order <?= e($activePickupOrder['order_number']) ?>">
            <div class="active-order-banner-icon" aria-hidden="true">
                <i class="bi bi-<?= $checkedIn ? 'geo-alt-fill' : 'bag-check' ?>"></i>
            </div>
            <div class="active-order-banner-copy">
                <strong class="active-order-banner-title">
                    <?= e($activePickupOrder['order_number']) ?>
                    <span class="active-order-banner-dot">·</span>
                    <?= e($status['label']) ?>
                </strong>
                <span class="active-order-banner-meta">
                    <?php if ($checkedIn): ?>
                    Checked in at <?= e(date('g:i A', strtotime((string) $activePickupOrder['customer_here_at']))) ?> — tap for details
                    <?php else: ?>
                    <?= (int) $activePickupOrder['item_count'] ?> item<?= (int) $activePickupOrder['item_count'] === 1 ? '' : 's' ?>
                    · <?= format_money($activePickupOrder['total']) ?>
                    · Tap to open order
                    <?php endif; ?>
                </span>
            </div>
        </a>
        <?php if ($checkedIn): ?>
        <a href="<?= e($orderUrl) ?>" class="active-order-banner-action" aria-label="View order details">
            View
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
        <?php else: ?>
        <button
            type="button"
            class="active-order-banner-action js-banner-im-here-btn"
            data-order-id="<?= (int) $activePickupOrder['id'] ?>"
            data-order-url="<?= e($orderUrl) ?>"
            aria-label="Notify Abdu Market that you have arrived"
        >
            I'm Here
            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
        </button>
        <?php endif; ?>
        <?php if ($callMart !== ''): ?>
        <?= $callMart ?>
        <?php endif; ?>
    </div>
</div>
<script>
document.body.classList.add('has-active-order-banner');
</script>
