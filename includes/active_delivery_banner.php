<?php

declare(strict_types=1);

/** @var array $activeDeliveryOrder */
$status = order_status_display((string) $activeDeliveryOrder['status']);
$orderUrl = 'orders.php?order=' . (int) $activeDeliveryOrder['id'];
$callMart = call_mart_button(true, 'active-order-banner-call');
$isOut = (string) $activeDeliveryOrder['status'] === 'out_for_delivery';
$deliveryAddress = format_delivery_address($activeDeliveryOrder);
?>
<div class="active-order-banner active-delivery-banner" id="activeDeliveryBanner" data-order-id="<?= (int) $activeDeliveryOrder['id'] ?>">
    <div class="active-order-banner-inner container">
        <a href="<?= e($orderUrl) ?>" class="active-order-banner-main" aria-label="View delivery order <?= e($activeDeliveryOrder['order_number']) ?>">
            <div class="active-order-banner-icon" aria-hidden="true">
                <i class="bi bi-<?= $isOut ? 'truck' : 'box-seam' ?>"></i>
            </div>
            <div class="active-order-banner-copy">
                <strong class="active-order-banner-title">
                    <?= e($activeDeliveryOrder['order_number']) ?>
                    <span class="active-order-banner-dot">·</span>
                    <?= e($status['label']) ?>
                </strong>
                <span class="active-order-banner-meta">
                    <?= (int) $activeDeliveryOrder['item_count'] ?> item<?= (int) $activeDeliveryOrder['item_count'] === 1 ? '' : 's' ?>
                    · <?= format_money($activeDeliveryOrder['total']) ?>
                    <?php if ($deliveryAddress !== ''): ?>
                    · <?= e($deliveryAddress) ?>
                    <?php else: ?>
                    · Tap for delivery details
                    <?php endif; ?>
                </span>
            </div>
        </a>
        <a href="<?= e($orderUrl) ?>" class="active-order-banner-action" aria-label="View order status">
            Status
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
        <?php if ($callMart !== ''): ?>
        <?= $callMart ?>
        <?php endif; ?>
    </div>
</div>
<script>
document.body.classList.add('has-active-order-banner');
</script>
