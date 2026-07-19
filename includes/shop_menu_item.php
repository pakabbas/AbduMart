<?php

declare(strict_types=1);

/** @var array $product */
$productUrl = 'product.php?id=' . (int) $product['id'];
?>
<article class="shop-menu-item">
    <a href="<?= e($productUrl) ?>" class="shop-menu-item-thumb catalog-tile-media<?= catalog_has_image($product['image_url'] ?? null) ? '' : ' show-initials' ?> text-decoration-none">
        <?= catalog_tile_media($product['name'], $product['image_url'] ?? null) ?>
    </a>
    <div class="shop-menu-item-body">
        <div class="shop-menu-item-top">
            <h3 class="shop-menu-item-name">
                <a href="<?= e($productUrl) ?>" class="text-decoration-none text-dark"><?= e($product['name']) ?></a>
            </h3>
            <?php if (!empty($product['is_featured'])): ?>
            <span class="shop-menu-item-badge">Popular</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($product['description'])): ?>
        <p class="shop-menu-item-desc"><?= e(mb_strimwidth((string) $product['description'], 0, 90, '...')) ?></p>
        <?php endif; ?>
        <div class="shop-menu-item-meta">
            <strong class="shop-menu-item-price"><?= format_money($product['price']) ?></strong>
            <?php if ((int) $product['inventory'] > 0 && (int) $product['inventory'] <= 5): ?>
            <span class="shop-menu-item-stock">Only <?= (int) $product['inventory'] ?> left</span>
            <?php elseif ((int) $product['inventory'] < 1): ?>
            <span class="shop-menu-item-stock shop-menu-item-stock-out">Sold out</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="shop-menu-item-action">
        <?php if (product_is_purchasable($product)): ?>
        <form method="post" action="<?= e(asset_url('mart-line.php')) ?>" class="mart-buy-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <button type="submit" class="shop-menu-add-btn" aria-label="Add <?= e($product['name']) ?> to cart">
                <i class="bi bi-plus-lg"></i>
            </button>
        </form>
        <?php else: ?>
        <button type="button" class="shop-menu-add-btn is-disabled" disabled aria-label="Unavailable">
            <i class="bi bi-dash-lg"></i>
        </button>
        <?php endif; ?>
    </div>
</article>
