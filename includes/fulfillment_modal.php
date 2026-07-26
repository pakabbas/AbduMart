<?php

declare(strict_types=1);

if (!delivery_enabled()) {
    return;
}

$currentFulfillment = fulfillment_mode();
$fulfillmentChosen = fulfillment_mode_chosen();
$deliveryFeeLabel = format_money(delivery_fee_amount());
$deliveryMinLabel = format_money(delivery_min_order_amount());
$cantonZipsLabel = implode(' & ', canton_delivery_zips());
?>
<div
    class="fulfillment-modal"
    id="fulfillmentModal"
    <?= $fulfillmentChosen ? 'hidden' : '' ?>
    data-chosen="<?= $fulfillmentChosen ? '1' : '0' ?>"
    data-mode="<?= e($currentFulfillment) ?>"
    role="dialog"
    aria-modal="true"
    aria-labelledby="fulfillmentModalTitle"
>
    <div class="fulfillment-modal-backdrop" aria-hidden="true"></div>
    <div class="fulfillment-modal-dialog">
        <h2 class="fulfillment-modal-title" id="fulfillmentModalTitle">Pickup or delivery?</h2>
        <p class="fulfillment-modal-lead">Choose how you want your order. You can switch anytime in the header.</p>
        <div class="fulfillment-modal-choices">
            <button type="button" class="fulfillment-choice js-fulfillment-pick" data-mode="pickup">
                <span class="fulfillment-choice-head">
                    <span class="fulfillment-choice-icon" aria-hidden="true"><i class="bi bi-shop-window"></i></span>
                    <span class="fulfillment-choice-copy">
                        <strong>Pickup</strong>
                        <em>Curbside at the store</em>
                    </span>
                </span>
                <ul class="fulfillment-choice-points">
                    <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Pick up at Abdu Market in Canton</span></li>
                    <li><i class="bi bi-cash" aria-hidden="true"></i><span>No delivery fee</span></li>
                </ul>
            </button>
            <button type="button" class="fulfillment-choice js-fulfillment-pick" data-mode="delivery">
                <span class="fulfillment-choice-head">
                    <span class="fulfillment-choice-icon" aria-hidden="true"><i class="bi bi-truck"></i></span>
                    <span class="fulfillment-choice-copy">
                        <strong>Delivery</strong>
                        <em>Brought to your door</em>
                    </span>
                </span>
                <ul class="fulfillment-choice-points">
                    <li><i class="bi bi-pin-map" aria-hidden="true"></i><span>Canton, MI only — ZIPs <?= e($cantonZipsLabel) ?></span></li>
                    <li><i class="bi bi-truck" aria-hidden="true"></i><span>Flat delivery fee from <?= e($deliveryFeeLabel) ?></span></li>
                    <li><i class="bi bi-bag-check" aria-hidden="true"></i><span>Minimum order <?= e($deliveryMinLabel) ?></span></li>
                </ul>
            </button>
        </div>
    </div>
</div>
