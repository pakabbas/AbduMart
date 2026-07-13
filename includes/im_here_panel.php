<?php

declare(strict_types=1);

/** @var array $order */
$canCheckIn = in_array($order['status'], ['paid', 'preparing', 'ready'], true);
?>
<?php if ($canCheckIn): ?>
<div class="im-here-panel p-4 rounded-3 mt-4" data-order-id="<?= (int) $order['id'] ?>">
    <?php if ($order['customer_here_at']): ?>
    <div class="text-center">
        <i class="bi bi-geo-alt-fill text-danger fs-1"></i>
        <h3 class="h5 mt-2">We're on our way!</h3>
        <p class="text-muted mb-0">You checked in at <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?>. Please stay in your vehicle.</p>
    </div>
    <?php else: ?>
    <div class="text-center">
        <p class="mb-3"><?= e(setting('mart.pickup_instructions', config('mart.pickup_instructions'))) ?></p>
        <button type="button" class="btn btn-danger btn-lg px-5 im-here-btn" data-order-id="<?= (int) $order['id'] ?>">
            <i class="bi bi-geo-alt-fill"></i> I'M HERE
        </button>
        <p class="small text-muted mt-2 mb-0">Tap when you've arrived at the curb</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
