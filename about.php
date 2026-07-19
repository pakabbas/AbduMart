<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'About Us';
$pageDescription = 'Learn about Abdu Market — your neighborhood grocery store with curbside pickup in Canton, Michigan.';
require __DIR__ . '/includes/header.php';
?>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-3">About Abdu Market</h1>
            <p class="lead text-muted">Your neighborhood market in Canton, Michigan — shop online and pick up curbside.</p>
            <p>
                Abdu Market brings everyday groceries and local favorites together in one convenient place.
                Browse our catalog from your phone or computer, pay securely online, then pull up to the curb
                and tap <strong>I'm Here</strong> when you arrive. We'll bring your order out to your car.
            </p>
            <p class="mb-0">
                Visit us at <?= e(setting('mart.address', config('mart.address'))) ?>
                or call <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) setting('mart.phone', config('mart.phone')))) ?>"><?= e(setting('mart.phone', config('mart.phone'))) ?></a>.
            </p>
            <div class="mt-4 d-flex flex-wrap gap-2">
                <a href="shop.php" class="btn btn-danger">Shop products</a>
                <a href="contact.php" class="btn btn-outline-secondary">Contact us</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
