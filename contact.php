<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Contact Us';
$pageDescription = 'Contact Abdu Market for curbside pickup help in Canton, Michigan.';
$martAddress = setting('mart.address', config('mart.address'));
$martPhone = setting('mart.phone', config('mart.phone'));
$mapEmbed = setting('mart.map_embed_url', config('mart.map_embed_url'));
require __DIR__ . '/includes/header.php';
?>

<section class="container py-5">
    <div class="row g-4">
        <div class="col-lg-5">
            <h1 class="mb-3">Contact Us</h1>
            <p class="text-muted">Questions about an order or curbside pickup? Reach us here.</p>
            <div class="mb-3">
                <h2 class="h6 text-uppercase text-muted">Store location</h2>
                <p class="mb-0"><?= e($martAddress) ?></p>
            </div>
            <div class="mb-3">
                <h2 class="h6 text-uppercase text-muted">Phone</h2>
                <p class="mb-0">
                    <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $martPhone)) ?>"><?= e($martPhone) ?></a>
                </p>
            </div>
            <div class="mb-4">
                <h2 class="h6 text-uppercase text-muted">Pickup</h2>
                <p class="mb-0"><?= e(setting('mart.pickup_instructions', config('mart.pickup_instructions'))) ?></p>
            </div>
            <a href="shop.php" class="btn btn-danger">Start shopping</a>
        </div>
        <div class="col-lg-7">
            <?php if ($mapEmbed): ?>
            <div class="order-map-wrap">
                <iframe
                    src="<?= e($mapEmbed) ?>"
                    title="Abdu Market location map"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
