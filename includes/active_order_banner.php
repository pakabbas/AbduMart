<?php

declare(strict_types=1);

/** @var array $activePickupOrder */
$status = order_status_display((string) $activePickupOrder['status']);
$orderUrl = 'orders.php?order=' . (int) $activePickupOrder['id'];
$checkedIn = !empty($activePickupOrder['customer_here_at']);
?>
<a href="<?= e($orderUrl) ?>" class="active-order-banner" id="activeOrderBanner" aria-label="View active order <?= e($activePickupOrder['order_number']) ?>">
    <div class="active-order-banner-inner container">
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
                · Tap to open order or notify the mart
                <?php endif; ?>
            </span>
        </div>
        <span class="active-order-banner-action" aria-hidden="true">
            <?php if ($checkedIn): ?>
            View
            <?php else: ?>
            I'm Here
            <?php endif; ?>
            <i class="bi bi-chevron-right"></i>
        </span>
    </div>
</a>
<script>
document.body.classList.add('has-active-order-banner');
</script>
