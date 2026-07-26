<?php

declare(strict_types=1);

if (!delivery_enabled()) {
    return;
}

$currentFulfillment = fulfillment_mode();
$fulfillmentChosen = fulfillment_mode_chosen();
$deliveryFeeLabel = format_money(delivery_fee_amount());
$deliveryMinLabel = format_money(delivery_min_order_amount());
$cantonZipsLabel = implode(' / ', canton_delivery_zips());
?>
<div
    class="fulfillment-modal"
    id="fulfillmentModal"
    hidden
    data-chosen="<?= $fulfillmentChosen ? '1' : '0' ?>"
    data-mode="<?= e($currentFulfillment) ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="fulfillmentModalTitle"
>
    <div class="fulfillment-modal-backdrop" data-fulfillment-dismiss></div>
    <div class="fulfillment-modal-dialog">
        <h2 class="fulfillment-modal-title" id="fulfillmentModalTitle">How would you like to get your order?</h2>
        <p class="fulfillment-modal-lead">Choose once — you can change this anytime from the header or cart.</p>
        <div class="fulfillment-modal-choices">
            <button type="button" class="fulfillment-choice js-fulfillment-pick" data-mode="pickup">
                <i class="bi bi-shop-window" aria-hidden="true"></i>
                <strong>Pickup</strong>
                <span>Curbside at Abdu Market</span>
            </button>
            <button type="button" class="fulfillment-choice js-fulfillment-pick" data-mode="delivery">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <strong>Delivery</strong>
                <span>Canton only · from <?= e($deliveryFeeLabel) ?> · <?= e($deliveryMinLabel) ?> min</span>
            </button>
        </div>
        <p class="fulfillment-modal-note">Delivery ZIPs: <?= e($cantonZipsLabel) ?></p>
    </div>
</div>
