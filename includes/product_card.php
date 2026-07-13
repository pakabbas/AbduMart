<?php

declare(strict_types=1);

/** @var array $product */
$productColClass = $productColClass ?? 'col-6 col-md-4 col-lg-3';
?>
<div class="<?= e($productColClass) ?>">
    <article class="product-card card h-100 border-0 shadow-sm">
        <div class="product-img catalog-tile-media<?= catalog_has_image($product['image_url'] ?? null) ? '' : ' show-initials' ?>">
            <?= catalog_tile_media($product['name'], $product['image_url'] ?? null) ?>
            <?php if ((int) $product['inventory'] > 0 && (int) $product['inventory'] <= 5): ?>
            <span class="stock-badge">Only <?= (int) $product['inventory'] ?> left</span>
            <?php elseif ((int) $product['inventory'] < 1): ?>
            <span class="stock-badge stock-out">Out of stock</span>
            <?php endif; ?>
            <?php if (!empty($product['is_featured'])): ?>
            <span class="featured-badge"><i class="bi bi-star-fill"></i> Featured</span>
            <?php endif; ?>
        </div>
        <div class="card-body d-flex flex-column">
            <span class="product-category"><?= e($product['category_name'] ?? 'General') ?></span>
            <h3 class="product-name"><?= e($product['name']) ?></h3>
            <p class="product-desc"><?= e(mb_strimwidth($product['description'] ?? '', 0, 80, '...')) ?></p>
            <div class="mt-auto d-flex justify-content-between align-items-center">
                <strong class="product-price"><?= format_money($product['price']) ?></strong>
                <?php if (is_logged_in()): ?>
                <?php if (product_is_purchasable($product)): ?>
                <form method="post" action="<?= e(asset_url('mart-line.php')) ?>" class="mart-buy-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </form>
                <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Unavailable</button>
                <?php endif; ?>
                <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-outline-danger">Sign in to buy</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
</div>
