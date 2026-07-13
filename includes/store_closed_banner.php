<?php

declare(strict_types=1);

/** @var array $storeStatus */
$storeStatus = $storeStatus ?? store_status();
if (!empty($storeStatus['open'])) {
    return;
}
?>
<div class="store-closed-banner" id="storeClosedBanner" role="status" aria-live="polite">
    <div class="store-closed-banner-inner container">
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <span><?= e($storeStatus['banner_message']) ?></span>
    </div>
</div>
<script>
document.body.classList.add('has-store-closed-banner');
</script>
