<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$productId = (int) ($_GET['id'] ?? 0);
$product = $productId > 0 ? get_product($productId) : null;

if (!$product || (int) ($product['is_active'] ?? 0) !== 1) {
    flash('warning', 'That product is not available.');
    redirect('shop.php');
}

$related = [];
if (!empty($product['category_id'])) {
    $related = array_values(array_filter(
        get_products([
            'category_id' => (int) $product['category_id'],
            'per_page' => 8,
        ]),
        static fn (array $row): bool => (int) $row['id'] !== $productId
    ));
    $related = array_slice($related, 0, 4);
}

$pageTitle = (string) $product['name'];
$pageDescription = trim((string) ($product['description'] ?? '')) !== ''
    ? (string) $product['description']
    : ('Buy ' . $product['name'] . ' for curbside pickup at Abdu Market.');
require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
            <?php if (!empty($product['category_name'])): ?>
            <li class="breadcrumb-item">
                <a href="shop.php?category=<?= (int) ($product['category_id'] ?? 0) ?>"><?= e($product['category_name']) ?></a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4 align-items-start">
        <div class="col-md-6">
            <div class="product-detail-media catalog-tile-media<?= catalog_has_image($product['image_url'] ?? null) ? '' : ' show-initials' ?>">
                <?= catalog_tile_media($product['name'], $product['image_url'] ?? null) ?>
                <?php if ((int) $product['inventory'] > 0 && (int) $product['inventory'] <= 5): ?>
                <span class="stock-badge">Only <?= (int) $product['inventory'] ?> left</span>
                <?php elseif ((int) $product['inventory'] < 1): ?>
                <span class="stock-badge stock-out">Out of stock</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="product-detail-panel">
                <?php if (!empty($product['category_name'])): ?>
                <span class="product-category"><?= e($product['category_name']) ?></span>
                <?php endif; ?>
                <h1 class="product-detail-title"><?= e($product['name']) ?></h1>
                <p class="product-detail-price text-danger"><?= format_money($product['price']) ?></p>
                <?php if (trim((string) ($product['description'] ?? '')) !== ''): ?>
                <p class="product-detail-desc text-muted"><?= e($product['description']) ?></p>
                <?php else: ?>
                <p class="product-detail-desc text-muted">Fresh from Abdu Market. Order online and pick up curbside in Canton, MI.</p>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
                    <?php if (product_is_purchasable($product)): ?>
                    <form method="post" action="<?= e(asset_url('mart-line.php')) ?>" class="mart-buy-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="bi bi-bag-plus"></i> Add to cart
                        </button>
                    </form>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary btn-lg" disabled>Currently unavailable</button>
                    <?php endif; ?>
                    <a href="shop.php" class="btn btn-outline-danger btn-lg">Continue shopping</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($related)): ?>
    <div class="mt-5 pt-2">
        <h2 class="section-title mb-3">More in <?= e($product['category_name'] ?? 'this category') ?></h2>
        <div class="row g-4">
            <?php foreach ($related as $product): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
